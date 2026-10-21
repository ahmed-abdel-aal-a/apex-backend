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
            $disk = $addon->disk ?? config('filesystems.default');
            $storage = Storage::disk($disk);
            
            foreach ($addon->file_paths as $path) {
                if ($storage->exists($path)) {
                    try {
                        $urls[] = $storage->temporaryUrl($path, now()->addMinutes(60));
                    } catch (\RuntimeException $e) {
                        $urls[] = $storage->url($path);
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
     */
    public function downloadFile(Request $request, $addonId, $fileIndex = 0)
    {
        $addon = OrderItemAddon::findOrFail($addonId);
        $fileIndex = (int) $fileIndex;
        $filePaths = is_array($addon->file_paths) ? array_values($addon->file_paths) : [];

        if (!isset($filePaths[$fileIndex])) {
            abort(404, 'File not found');
        }

        $path = $filePaths[$fileIndex];

        // Strict Path Traversal Prevention
        if (!in_array($path, $filePaths, true) || str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            abort(403, 'Invalid file path');
        }

        $disk = $addon->disk ?? config('filesystems.default');
        $storage = Storage::disk($disk);

        if (!$storage->exists($path)) {
            abort(404, 'File not found on storage');
        }

        return $storage->download($path);
    }
}
