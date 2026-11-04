<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\TemporaryUpload;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanOldPrintFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'print:clean-old-files 
                            {--days=14 : Number of days for delivered orders and abandoned carts retention cutoff} 
                            {--temp-hours=24 : Number of hours for temporary upload cutoff}
                            {--dry-run : Simulate the cleanup without deleting files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'High-performance print files retention and cleanup engine using chunkById(100)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $tempHours = (int) $this->option('temp-hours');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Starting high-performance print file retention engine" . ($dryRun ? " [DRY RUN]" : "") . "...");

        $deletedChunks = 0;
        $deletedTempUploads = 0;
        $deletedCartFiles = 0;
        $deletedOrderFiles = 0;
        $freedBytes = 0;

        // -------------------------------------------------------------
        // 1. Clean Abandoned Upload Chunks (older than $tempHours hours)
        // -------------------------------------------------------------
        $chunkCutoff = Carbon::now()->subHours($tempHours);
        foreach (['chunks', 'temp_chunks'] as $baseDir) {
            if (Storage::disk('local')->exists($baseDir)) {
                $chunkDirs = Storage::disk('local')->directories($baseDir);
                foreach ($chunkDirs as $dir) {
                    try {
                        $lastModified = Storage::disk('local')->lastModified($dir);
                        if ($lastModified && Carbon::createFromTimestamp($lastModified)->lt($chunkCutoff)) {
                            // Calculate size before deleting
                            $files = Storage::disk('local')->allFiles($dir);
                            foreach ($files as $f) {
                                try {
                                    $freedBytes += (int)Storage::disk('local')->size($f);
                                } catch (\Throwable $e) {}
                            }

                            if (!$dryRun) {
                                Storage::disk('local')->deleteDirectory($dir);
                            }
                            $deletedChunks++;
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("Failed to clean chunk directory {$dir}: " . $e->getMessage());
                    }
                }
            }
        }
        $this->line("• Cleaned abandoned chunk directories: {$deletedChunks}");

        // -------------------------------------------------------------
        // 2. Clean Abandoned Temporary Uploads (older than $tempHours hours)
        // -------------------------------------------------------------
        $tempCutoff = Carbon::now()->subHours($tempHours);
        TemporaryUpload::where('created_at', '<', $tempCutoff)
            ->chunkById(100, function ($uploads) use ($dryRun, &$deletedTempUploads, &$freedBytes) {
                foreach ($uploads as $temp) {
                    $path = $temp->file_path;
                    $disk = $temp->disk ?? 'local';

                    if (is_string($path) && !empty($path)) {
                        if (!$this->isFileProtected($path)) {
                            if (Storage::disk($disk)->exists($path)) {
                                $size = (int)($temp->file_size ?: 0);
                                if ($size === 0) {
                                    try {
                                        $size = (int)Storage::disk($disk)->size($path);
                                    } catch (\Throwable $e) {}
                                }

                                if (!$dryRun) {
                                    Storage::disk($disk)->delete($path);
                                }
                                $freedBytes += $size;
                                $deletedTempUploads++;
                            }
                        }
                    }

                    if (!$dryRun) {
                        $temp->delete();
                    }
                }
            });
        $this->line("• Cleaned abandoned temporary uploads: {$deletedTempUploads}");

        // -------------------------------------------------------------
        // 3. Clean Abandoned Cart Items (older than $days days)
        // -------------------------------------------------------------
        $cartCutoff = Carbon::now()->subDays($days);
        CartItem::where('item_type', 'printing')
            ->whereNotNull('uploaded_files')
            ->where('file_status', '!=', 'cleaned')
            ->where(function ($q) use ($cartCutoff) {
                $q->where('quote_expires_at', '<', $cartCutoff)
                  ->orWhereHas('cart', function ($cartQuery) use ($cartCutoff) {
                      $cartQuery->where('status', 'abandoned')
                                ->orWhere('updated_at', '<', $cartCutoff);
                  });
            })
            ->chunkById(100, function ($abandonedItems) use ($dryRun, &$deletedCartFiles, &$freedBytes) {
                foreach ($abandonedItems as $item) {
                    $files = is_string($item->uploaded_files) 
                        ? json_decode($item->uploaded_files, true) 
                        : $item->uploaded_files;

                    if (!is_array($files)) {
                        continue;
                    }

                    $modified = false;
                    foreach ($files as $category => $fileData) {
                        $path = is_array($fileData) ? ($fileData['path'] ?? null) : $fileData;
                        $disk = is_array($fileData) ? ($fileData['disk'] ?? 'local') : 'local';

                        if (!is_string($path) || empty($path) || $path === 'deleted_by_system' || $path === 'deleted_by_retention') {
                            continue;
                        }

                        // Strict Protection Gate: Never delete if used in ANY order or active cart
                        if ($this->isFileProtected($path, $item->id)) {
                            continue;
                        }

                        if (Storage::disk($disk)->exists($path)) {
                            $size = (is_array($fileData) && !empty($fileData['size'])) ? (int)$fileData['size'] : 0;
                            if ($size === 0) {
                                try {
                                    $size = (int)Storage::disk($disk)->size($path);
                                } catch (\Throwable $e) {}
                            }

                            if (!$dryRun) {
                                Storage::disk($disk)->delete($path);
                            }
                            $freedBytes += $size;
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

                        // Clean empty parent folder if on local
                        $folder = "print_jobs/{$item->id}";
                        if (Storage::disk('local')->exists($folder) && count(Storage::disk('local')->allFiles($folder)) === 0) {
                            Storage::disk('local')->deleteDirectory($folder);
                        }
                    }
                }
            });
        $this->line("• Cleaned abandoned cart files: {$deletedCartFiles}");

        // -------------------------------------------------------------
        // 4. Archive Delivered/Completed Orders (older than $days days)
        // -------------------------------------------------------------
        $orderCutoff = Carbon::now()->subDays($days);
        Order::whereIn('status', ['delivered', 'completed'])
            ->where('updated_at', '<', $orderCutoff)
            ->whereHas('items', function ($q) {
                $q->where('item_type', 'printing')
                  ->where('file_status', '!=', 'cleaned_by_retention')
                  ->whereNotNull('uploaded_files');
            })
            ->chunkById(100, function ($orders) use ($dryRun, &$deletedOrderFiles, &$freedBytes) {
                foreach ($orders as $order) {
                    foreach ($order->items as $item) {
                        if ($item->item_type !== 'printing' || $item->file_status === 'cleaned_by_retention' || empty($item->uploaded_files)) {
                            continue;
                        }

                        $files = is_array($item->uploaded_files) 
                            ? $item->uploaded_files 
                            : (json_decode($item->uploaded_files, true) ?: []);

                        if (!is_array($files)) {
                            continue;
                        }

                        $modified = false;
                        foreach ($files as $category => $fileData) {
                            $path = is_array($fileData) ? ($fileData['path'] ?? null) : $fileData;
                            $disk = is_array($fileData) ? ($fileData['disk'] ?? 'local') : 'local';

                            if (!is_string($path) || empty($path) || $path === 'deleted_by_retention' || $path === 'deleted_by_system') {
                                continue;
                            }

                            if (Storage::disk($disk)->exists($path)) {
                                $size = (is_array($fileData) && !empty($fileData['size'])) ? (int)$fileData['size'] : 0;
                                if ($size === 0) {
                                    try {
                                        $size = (int)Storage::disk($disk)->size($path);
                                    } catch (\Throwable $e) {}
                                }

                                if (!$dryRun) {
                                    Storage::disk($disk)->delete($path);
                                }
                                $freedBytes += $size;
                                $deletedOrderFiles++;
                                $modified = true;
                            }

                            if (is_array($files[$category])) {
                                $files[$category]['path'] = 'deleted_by_retention';
                            } else {
                                $files[$category] = 'deleted_by_retention';
                            }
                        }

                        if ($modified && !$dryRun) {
                            $item->uploaded_files = $files;
                            $item->file_status = 'cleaned_by_retention';
                            $item->save();
                        }
                    }
                }
            });
        $this->line("• Archived delivered/completed order files: {$deletedOrderFiles}");

        // -------------------------------------------------------------
        // Summary & Formatted Output
        // -------------------------------------------------------------
        $totalFilesCleaned = $deletedTempUploads + $deletedCartFiles + $deletedOrderFiles;
        $formattedFreedSpace = $this->formatBytes($freedBytes);

        $this->newLine();
        $this->info("=================================================");
        $this->info("         PRINT RETENTION SUMMARY " . ($dryRun ? "[DRY RUN]" : ""));
        $this->info("=================================================");
        $this->table(
            ['Retention Metric', 'Cleaned Value'],
            [
                ['Abandoned Chunk Folders', (string)$deletedChunks],
                ['Abandoned Temporary Uploads', (string)$deletedTempUploads],
                ['Abandoned Cart Files', (string)$deletedCartFiles],
                ['Archived Delivered Order Files (>14d)', (string)$deletedOrderFiles],
                ['Total Files Purged / Archived', (string)$totalFilesCleaned],
                ['Estimated Storage Freed', $formattedFreedSpace],
            ]
        );

        $this->info("Print file retention engine finished successfully.");
        return 0;
    }

    /**
     * Strict Safety Gate: Verify if file path is used in ANY order_items or active carts.
     */
    protected function isFileProtected(string $path, int $excludeCartItemId = 0): bool
    {
        // 1. NEVER delete files referenced in ANY active / non-archived order_item
        $inOrderItem = OrderItem::where('uploaded_files', 'like', "%{$path}%")
            ->where('file_status', '!=', 'cleaned_by_retention')
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', ['cancelled', 'expired']);
            })->exists();
        if ($inOrderItem) {
            return true;
        }

        // 2. NEVER delete files referenced in ANY order_item_addons
        $inOrderAddon = OrderItemAddon::where('file_paths', 'like', "%{$path}%")->exists();
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

    /**
     * Format bytes into human-readable units.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int)$pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
