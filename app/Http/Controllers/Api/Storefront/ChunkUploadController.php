<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\TemporaryUpload;

class ChunkUploadController extends Controller
{
    public function upload(Request $request)
    {
        $dangerousExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'sh', 'bat', 'js', 'html', 'htm', 'cgi', 'pl'];
        
        $request->validate([
            'file' => 'required|file',
            'resumableIdentifier' => 'required|string',
            'resumableChunkNumber' => 'required|integer',
            'resumableTotalChunks' => 'required|integer',
            'resumableFilename' => 'required|string',
            'category' => 'required|string'
        ]);

        $file = $request->file('file');
        // Sanitize identifier to prevent Directory Traversal
        $identifier = basename($request->input('resumableIdentifier'));
        $chunkNumber = (int) $request->input('resumableChunkNumber');
        $totalChunks = (int) $request->input('resumableTotalChunks');
        $filename = $request->input('resumableFilename');
        $category = $request->input('category');

        $chunkDir = 'chunks/' . $identifier;
        if (!Storage::disk('local')->exists($chunkDir)) {
            Storage::disk('local')->makeDirectory($chunkDir);
        }

        // Save chunk
        $chunkPath = $chunkDir . '/' . $chunkNumber;
        Storage::disk('local')->putFileAs($chunkDir, $file, $chunkNumber);

        // Check if all chunks are uploaded
        $uploadedChunks = count(Storage::disk('local')->files($chunkDir));

        if ($uploadedChunks === $totalChunks) {
            return Cache::lock('assemble_chunk_' . $identifier, 30)->block(10, function () use ($chunkDir, $totalChunks, $identifier, $filename, $category, $dangerousExtensions, $request) {
                $sessionId = $request->header('X-Session-ID') ?: $request->input('session_id');

                // If chunk directory was already removed by another concurrent process that assembled the file:
                if (!Storage::disk('local')->exists($chunkDir)) {
                    $existingUpload = TemporaryUpload::where('category', $category)
                        ->where(function($q) use ($sessionId) {
                            $q->where('session_id', $sessionId)
                              ->orWhere('user_id', auth()->id());
                        })
                        ->where('file_path', 'like', 'temp_uploads/%')
                        ->latest()
                        ->first();

                    if ($existingUpload) {
                        return response()->json([
                            'message' => 'Upload complete',
                            'file_id' => $existingUpload->id,
                            'file_path' => $existingUpload->file_path,
                            'page_count' => $existingUpload->page_count,
                            'category' => $category,
                            'status' => 'completed'
                        ]);
                    }
                }

                // Verify all chunks are present before assembly
                for ($i = 1; $i <= $totalChunks; $i++) {
                    if (!Storage::disk('local')->exists($chunkDir . '/' . $i)) {
                        return response()->json([
                            'message' => 'Waiting for missing chunk ' . $i,
                            'status' => 'uploading'
                        ]);
                    }
                }

                // Concatenate all chunks
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (empty($extension) || in_array($extension, $dangerousExtensions)) {
                    $extension = 'tmp';
                }
                
                $finalFilename = Str::uuid() . '.' . $extension;
                $finalPath = 'temp_uploads/' . $finalFilename;
                $absoluteFinalPath = Storage::disk('local')->path($finalPath);

                // Create temp_uploads directory on local disk if not exists
                if (!Storage::disk('local')->exists('temp_uploads')) {
                    Storage::disk('local')->makeDirectory('temp_uploads');
                }

                // Open final file for writing
                $finalFile = fopen($absoluteFinalPath, 'ab');

                for ($i = 1; $i <= $totalChunks; $i++) {
                    $chunkFile = Storage::disk('local')->path($chunkDir . '/' . $i);
                    if (file_exists($chunkFile)) {
                        $chunkHandle = fopen($chunkFile, 'rb');
                        stream_copy_to_stream($chunkHandle, $finalFile);
                        fclose($chunkHandle);
                    }
                }

                fclose($finalFile);

                // Clean up temporary chunks immediately to save server disk space
                Storage::disk('local')->deleteDirectory($chunkDir);

                // MIME Validation to prevent Script Injection
                $mimeType = mime_content_type($absoluteFinalPath);
                $dangerousMimes = ['text/x-php', 'text/html', 'application/x-httpd-php', 'application/javascript', 'text/javascript', 'application/x-sh', 'application/x-executable'];
                
                if (in_array($mimeType, $dangerousMimes) || in_array($extension, $dangerousExtensions)) {
                    if (file_exists($absoluteFinalPath)) {
                        @unlink($absoluteFinalPath);
                    }
                    return response()->json(['error' => 'نوع الملف غير مسموح به أمنياً.'], 403);
                }

                // PDF Page Count Parsing for Chunk Uploads
                $pageCount = null;
                if ($extension === 'pdf' || $mimeType === 'application/pdf') {
                    try {
                        $parser = new \Smalot\PdfParser\Parser();
                        $pdf = $parser->parseFile($absoluteFinalPath);
                        $pageCount = count($pdf->getPages());
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning('PDF parsing failed in ChunkUpload: ' . $e->getMessage());
                    }
                }

                // Save to DB
                $uploadId = Str::uuid()->toString();
                $tempUpload = TemporaryUpload::create([
                    'id' => $uploadId,
                    'session_id' => $sessionId,
                    'user_id' => auth()->id(),
                    'file_path' => $finalPath,
                    'category' => $category,
                    'page_count' => $pageCount,
                ]);

                return response()->json([
                    'message' => 'Upload complete',
                    'file_id' => $uploadId,
                    'file_path' => $finalPath,
                    'page_count' => $pageCount,
                    'category' => $category,
                    'status' => 'completed'
                ]);
            });
        }

        return response()->json([
            'message' => 'Chunk ' . $chunkNumber . ' uploaded successfully',
            'status' => 'uploading'
        ]);
    }
}
