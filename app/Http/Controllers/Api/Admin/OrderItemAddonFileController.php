<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\OrderItemAddon;

class OrderItemAddonFileController extends Controller
{
    /**
     * Get temporary URLs for the files attached to an order item addon.
     */
    public function getFiles($addonId)
    {
        $addon = OrderItemAddon::findOrFail($addonId);

        $urls = [];
        if (!empty($addon->file_paths)) {
            $filePaths = is_array($addon->file_paths) ? array_values($addon->file_paths) : [];
            foreach ($filePaths as $fileEntry) {
                $path = is_array($fileEntry) ? ($fileEntry['path'] ?? null) : $fileEntry;
                $disk = is_array($fileEntry) ? ($fileEntry['disk'] ?? $addon->disk ?? 'local') : ($addon->disk ?? 'local');

                if (is_string($path) && !empty($path) && $path !== 'deleted_by_retention' && $path !== 'deleted_by_system') {
                    $storage = Storage::disk($disk);
                    if ($storage->exists($path)) {
                        try {
                            $urls[] = $storage->temporaryUrl($path, now()->addMinutes(60));
                        } catch (\Throwable $e) {
                            $urls[] = $storage->url($path);
                        }
                    }
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'urls' => $urls
            ]
        ]);
    }

    /**
     * Download a specific file attached to an order item addon.
     * Supports JSON response with Presigned URL and direct file streaming.
     */
    public function downloadFile(Request $request, $addonId, $fileIndex = 0)
    {
        $addon = OrderItemAddon::findOrFail($addonId);
        $fileIndex = (int) $fileIndex;
        $filePaths = is_array($addon->file_paths) ? array_values($addon->file_paths) : [];

        $isJsonRequest = $request->expectsJson() || $request->wantsJson() || $request->query('json') === '1';
        $isDirect = $request->query('direct') === '1';

        if (!isset($filePaths[$fileIndex])) {
            if ($isJsonRequest) {
                return response()->json(['message' => 'الملف المطلوب غير موجود في هذه الخدمة.'], 404);
            }
            abort(404, 'الملف المطلوب غير موجود في هذه الخدمة.');
        }

        $fileEntry = $filePaths[$fileIndex];
        $path = is_array($fileEntry) ? ($fileEntry['path'] ?? null) : $fileEntry;
        $disk = is_array($fileEntry) ? ($fileEntry['disk'] ?? $addon->disk ?? 'local') : ($addon->disk ?? 'local');
        $fileName = is_array($fileEntry) ? ($fileEntry['name'] ?? basename($path)) : basename($path);

        if (!is_string($path) || empty($path)) {
            if ($isJsonRequest) {
                return response()->json(['message' => 'مسار الملف غير متاح.'], 404);
            }
            abort(404, 'مسار الملف غير متاح.');
        }

        // الحماية من الملفات المؤرشفة وفق سياسة الاحتفاظ (Retention Policy)
        if ($path === 'deleted_by_retention' || $path === 'deleted_by_system') {
            if ($isJsonRequest) {
                return response()->json(['message' => 'تم أرشفة وحذف هذا الملف تلقائياً لمرور أكثر من 14 يوماً على تسليم الطلب.'], 410);
            }
            abort(410, 'تم أرشفة وحذف هذا الملف تلقائياً لمرور أكثر من 14 يوماً على تسليم الطلب.');
        }

        // الحماية الصارمة من ثغرات Path Traversal
        if (str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            if ($isJsonRequest) {
                return response()->json(['message' => 'مسار الملف غير مسموح به أمنياً.'], 403);
            }
            abort(403, 'مسار الملف غير مسموح به أمنياً.');
        }

        if (!Storage::disk($disk)->exists($path)) {
            if ($isJsonRequest) {
                return response()->json(['message' => "الملف غير موجود على وحدة التخزين ({$disk})."], 404);
            }
            abort(404, "الملف غير موجود على وحدة التخزين ({$disk}).");
        }

        // [RFC 6266]: تشفير الأسماء العربية والأجنبية بشكل قياسي
        $encodedFileName = rawurlencode($fileName);
        $asciiFallback = preg_replace('/[^\x20-\x7e]/', '_', $fileName) ?: 'download';
        $contentDisposition = "attachment; filename=\"{$asciiFallback}\"; filename*=UTF-8''{$encodedFileName}";

        // التخزين السحابي (Backblaze B2 / R2)
        if ($disk === 'r2') {
            try {
                $tempUrl = Storage::disk('r2')->temporaryUrl($path, now()->addMinutes(30), [
                    'ResponseContentDisposition' => $contentDisposition
                ]);

                if ($isJsonRequest && !$isDirect) {
                    return response()->json([
                        'status' => 'success',
                        'download_url' => $tempUrl,
                        'name' => $fileName,
                        'disk' => 'r2',
                    ]);
                }

                return redirect()->away($tempUrl);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("R2 temporaryUrl generation failed for addon file, falling back to stream download: " . $e->getMessage());
                return Storage::disk('r2')->download($path, $fileName);
            }
        }

        // التخزين المحلي (Local Disk)
        if ($isJsonRequest && !$isDirect) {
            $token = $request->bearerToken() ?: $request->query('token');
            $localDirectUrl = $request->url() . '?direct=1' . ($token ? '&token=' . urlencode($token) : '');
            return response()->json([
                'status' => 'success',
                'download_url' => $localDirectUrl,
                'name' => $fileName,
                'disk' => 'local',
            ]);
        }

        return Storage::disk($disk)->download($path, $fileName);
    }
}
