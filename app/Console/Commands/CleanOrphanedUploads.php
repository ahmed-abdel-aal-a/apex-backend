<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TemporaryUpload;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanOrphanedUploads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'uploads:clean-orphaned';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean orphaned temporary uploads older than 48 hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cutoff = Carbon::now()->subHours(48);

        // 1. Clean Temporary Uploads (Older than 48h and not referenced in orders)
        $orphans = TemporaryUpload::where('created_at', '<', $cutoff)->get();
        foreach ($orphans as $upload) {
            $isReferenced = \App\Models\OrderItem::where('uploaded_files', 'like', "%{$upload->file_path}%")->exists()
                || \App\Models\OrderItemAddon::where('file_paths', 'like', "%{$upload->file_path}%")->exists();
            if (!$isReferenced && Storage::disk('local')->exists($upload->file_path)) {
                Storage::disk('local')->delete($upload->file_path);
            }
            $upload->delete();
        }

        // 2. Clean CartItems Uploads (Orphaned in un-converted carts over 48h)
        $expiredCartItems = \App\Models\CartItem::where('created_at', '<', $cutoff)
            ->where('file_status', '!=', 'expired')
            ->whereNotNull('uploaded_files')
            ->whereHas('cart', function ($q) {
                $q->whereIn('status', ['active', 'pending', 'abandoned']);
            })
            ->whereDoesntHave('cart', function ($q) {
                $q->where('status', 'converted');
            })
            ->get();

        $cartFilesCount = 0;
        foreach ($expiredCartItems as $item) {
            $hasDeleted = false;
            $files = is_array($item->uploaded_files) 
                ? $item->uploaded_files 
                : (json_decode($item->uploaded_files, true) ?: []);

            if (is_array($files)) {
                foreach ($files as $fileData) {
                    $path = is_array($fileData) ? ($fileData['path'] ?? null) : $fileData;
                    if (is_string($path) && !empty($path)) {
                        // Strict Protection Gate: Never delete if referenced in ANY order item
                        $isReferencedInOrder = \App\Models\OrderItem::where('uploaded_files', 'like', "%{$path}%")->exists()
                            || \App\Models\OrderItemAddon::where('file_paths', 'like', "%{$path}%")->exists();
                        
                        if (!$isReferencedInOrder && Storage::disk('local')->exists($path)) {
                            Storage::disk('local')->delete($path);
                            $hasDeleted = true;
                            $cartFilesCount++;
                        }
                    }
                }
            }
            
            // تفريغ uploaded_files وتحديث الحالة لمنع عمل Checkout لملف ممسوح
            $item->update([
                'file_status' => 'expired',
                'uploaded_files' => []
            ]);
        }

        $this->info("Cleaned {$orphans->count()} temp uploads and {$cartFilesCount} abandoned cart item files.");

        // 3. Clean Orphaned Chunks in storage/app/chunks/ older than 24 hours
        $chunkBaseDir = 'chunks';
        $chunksCleaned = 0;
        if (Storage::disk('local')->exists($chunkBaseDir)) {
            $directories = Storage::disk('local')->directories($chunkBaseDir);
            foreach ($directories as $dir) {
                $lastModified = Storage::disk('local')->lastModified($dir);
                if (Carbon::createFromTimestamp($lastModified)->lessThan(Carbon::now()->subHours(24))) {
                    Storage::disk('local')->deleteDirectory($dir);
                    $chunksCleaned++;
                }
            }
        }
        $this->info("Cleaned {$chunksCleaned} abandoned chunk directories.");
    }
}
