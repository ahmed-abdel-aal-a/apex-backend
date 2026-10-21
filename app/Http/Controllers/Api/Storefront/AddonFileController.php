<?php
namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\TemporaryUpload;

class AddonFileController extends Controller
{
    public function getTempFile(Request $request, $tempFileId)
    {
        $tempUpload = TemporaryUpload::findOrFail($tempFileId);
        
        $sessionId = $request->header('X-Session-ID') ?? $request->query('session_id') ?? session()->getId();
        $userId = auth('sanctum')->id();

        $isOwner = false;
        if ($userId && $tempUpload->user_id === $userId) {
            $isOwner = true;
        } elseif ($sessionId && $tempUpload->session_id === $sessionId) {
            $isOwner = true;
        }

        if (!$isOwner) {
            abort(403, 'Unauthorized access to this file.');
        }

        $disk = $tempUpload->disk ?? config('filesystems.default');
        $storage = Storage::disk($disk);
        
        try {
            $url = $storage->temporaryUrl($tempUpload->file_path, now()->addMinutes(30));
        } catch (\RuntimeException $e) {
            // Fallback for local/public disks that do not support temporary URLs out of the box
            $url = $storage->url($tempUpload->file_path);
        }

        return redirect()->away($url);
    }
}
