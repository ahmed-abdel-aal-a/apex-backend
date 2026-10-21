<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\TemporaryUpload;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanOldPrintFiles extends Command
{
    protected $signature = 'print:clean-old-files 
                            {--days=14 : Number of days for abandoned cart cutoff} 
                            {--temp-hours=24 : Number of hours for temporary upload cutoff}
                            {--dry-run : Simulate the cleanup without deleting files}';

    protected $description = 'Safely clean abandoned temporary uploads and abandoned cart files while strictly protecting all order items';

    public function handle()
    {
        $days = (int) $this->option('days');
        $tempHours = (int) $this->option('temp-hours');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Starting print file cleanup" . ($dryRun ? " [DRY RUN]" : "") . "...");

        $deletedChunks = 0;
        $deletedTempUploads = 0;
        $deletedCartFiles = 0;

        // -------------------------------------------------------------
        // 1. Clean Abandoned Upload Chunks (older than $tempHours hours)
        // -------------------------------------------------------------
        $chunkCutoff = Carbon::now()->subHours($tempHours);
        if (Storage::disk('local')->exists('chunks')) {
            $chunkDirs = Storage::disk('local')->directories('chunks');
            foreach ($chunkDirs as $dir) {
                $lastModified = Storage::disk('local')->lastModified($dir);
                if ($lastModified && Carbon::createFromTimestamp($lastModified)->lt($chunkCutoff)) {
                    if (!$dryRun) {
                        Storage::disk('local')->deleteDirectory($dir);
                    }
                    $deletedChunks++;
                }
            }
        }
        $this->info("Cleaned abandoned chunk folders: {$deletedChunks}");

        // -------------------------------------------------------------
        // 2. Clean Abandoned Temporary Uploads (older than $tempHours hours)
        // -------------------------------------------------------------
        $tempCutoff = Carbon::now()->subHours($tempHours);
        $abandonedTempUploads = TemporaryUpload::where('created_at', '<', $tempCutoff)->cursor();

        foreach ($abandonedTempUploads as $temp) {
            $path = $temp->file_path;
            if (is_string($path) && !empty($path)) {
                if (!$this->isFileProtected($path)) {
                    if (Storage::disk('local')->exists($path)) {
                        if (!$dryRun) {
                            Storage::disk('local')->delete($path);
                        }
                        $deletedTempUploads++;
                    }
                }
            }
            if (!$dryRun) {
                $temp->delete();
            }
        }
        $this->info("Cleaned abandoned temporary uploads: {$deletedTempUploads}");

        // -------------------------------------------------------------
        // 3. Clean Abandoned Cart Items (older than $days days)
        // -------------------------------------------------------------
        $cartCutoff = Carbon::now()->subDays($days);

        // Find print items in abandoned or inactive carts
        $abandonedItems = CartItem::where('item_type', 'printing')
            ->whereNotNull('uploaded_files')
            ->where('file_status', '!=', 'cleaned')
            ->where(function ($q) use ($cartCutoff) {
                $q->where('quote_expires_at', '<', $cartCutoff)
                  ->orWhereHas('cart', function ($cartQuery) use ($cartCutoff) {
                      $cartQuery->where('status', 'abandoned')
                                ->orWhere('updated_at', '<', $cartCutoff);
                  });
            })
            ->cursor();

        foreach ($abandonedItems as $item) {
            $files = is_string($item->uploaded_files) 
                ? json_decode($item->uploaded_files, true) 
                : $item->uploaded_files;

            if (!is_array($files)) {
                continue;
            }

            $modified = false;
            foreach ($files as $category => $fileData) {
                $path = null;
                if (is_string($fileData)) {
                    $path = $fileData;
                } elseif (is_array($fileData) && isset($fileData['path']) && is_string($fileData['path'])) {
                    $path = $fileData['path'];
                }

                if (!is_string($path) || empty($path) || $path === 'deleted_by_system') {
                    continue;
                }

                // 100% Protection Gate: Never delete if used in ANY order or active cart
                if ($this->isFileProtected($path, $item->id)) {
                    $this->line("Skipping protected file: {$path}");
                    continue;
                }

                if (Storage::disk('local')->exists($path)) {
                    if (!$dryRun) {
                        Storage::disk('local')->delete($path);
                    }
                    $deletedCartFiles++;
                    $modified = true;
                }

                if (is_array($files[$category])) {
                    $files[$category]['path'] = 'deleted_by_system';
                } else {
                    $files[$category] = 'deleted_by_system';
                }
            }

            if ($modified && !$dryRun) {
                $item->uploaded_files = $files;
                $item->file_status = 'cleaned';
                $item->save();

                // Clean empty parent folder
                $folder = "print_jobs/{$item->id}";
                if (Storage::disk('local')->exists($folder) && count(Storage::disk('local')->allFiles($folder)) === 0) {
                    Storage::disk('local')->deleteDirectory($folder);
                }
            }
        }
        $this->info("Cleaned abandoned cart files: {$deletedCartFiles}");
        $this->info("Cleanup completed successfully.");
    }

    /**
     * Strict Safety Gate: Verify if file path is used in ANY order_items or active carts.
     */
    protected function isFileProtected(string $path, int $excludeCartItemId = 0): bool
    {
        // 1. NEVER delete files referenced in ANY active order_item (excluding cancelled/expired quotes)
        $inOrderItem = OrderItem::where('uploaded_files', 'like', "%{$path}%")
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', ['cancelled', 'expired']);
            })->exists();
        if ($inOrderItem) {
            return true;
        }

        // 2. NEVER delete files referenced in ANY order_item_addons
        $inOrderAddon = OrderItemAddon::where('file_path', $path)->exists();
        if ($inOrderAddon) {
            return true;
        }

        // 3. NEVER delete files referenced in recent active/pending carts
        $inActiveCart = CartItem::where('id', '!=', $excludeCartItemId)
            ->where('uploaded_files', 'like', "%{$path}%")
            ->whereHas('cart', function ($q) {
                $q->whereIn('status', ['active', 'pending'])
                  ->where('updated_at', '>=', Carbon::now()->subDays(14));
            })->exists();

        return $inActiveCart;
    }
}
