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
                        // Check protection using path and fileBase
                        $isProtected = $this->isFileProtected($path);

                        // Also verify if $temp->id is linked in any active cart addon
                        if (!$isProtected) {
                            $isTempIdInActiveCart = \App\Models\CartItemAddon::whereHas('cartItem.cart', function ($q) {
                                $q->whereIn('status', ['active', 'pending'])
                                  ->where('updated_at', '>=', Carbon::now()->subDays(14));
                            })->where(function ($q) use ($temp) {
                                $q->whereJsonContains('temp_file_ids', (string)$temp->id)
                                  ->orWhereJsonContains('temp_file_ids', $temp->id)
                                  ->orWhere('temp_file_ids', 'like', '%"' . $temp->id . '"%');
                            })->exists();

                            if ($isTempIdInActiveCart) {
                                $isProtected = true;
                            }
                        }

                        if (!$isProtected) {
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
            ->where(function ($q) {
                $q->whereHas('items', function ($itemQ) {
                    $itemQ->where('item_type', 'printing')
                          ->where('file_status', '!=', 'cleaned_by_retention')
                          ->whereNotNull('uploaded_files');
                })->orWhereHas('items.addons', function ($addonQ) {
                    $addonQ->whereNotNull('file_paths')
                           ->where('file_paths', 'not like', '%deleted_by_retention%');
                });
            })
            ->with(['items.addons'])
            ->chunkById(100, function ($orders) use ($dryRun, &$deletedOrderFiles, &$freedBytes) {
                foreach ($orders as $order) {
                    foreach ($order->items as $item) {
                        // 1. Archive print item files
                        if ($item->item_type === 'printing' && $item->file_status !== 'cleaned_by_retention' && !empty($item->uploaded_files)) {
                            $files = is_array($item->uploaded_files) 
                                ? $item->uploaded_files 
                                : (json_decode($item->uploaded_files, true) ?: []);

                            if (is_array($files)) {
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

                        // 2. Archive order item addons files
                        if ($item->addons->isNotEmpty()) {
                            foreach ($item->addons as $addon) {
                                $addonFiles = is_array($addon->file_paths)
                                    ? $addon->file_paths
                                    : (json_decode($addon->file_paths ?? '[]', true) ?: []);

                                if (!empty($addonFiles) && is_array($addonFiles)) {
                                    $addonModified = false;
                                    foreach ($addonFiles as $aIdx => $aEntry) {
                                        $aPath = is_array($aEntry) ? ($aEntry['path'] ?? null) : $aEntry;
                                        $aDisk = is_array($aEntry) ? ($aEntry['disk'] ?? $addon->disk ?? 'local') : ($addon->disk ?? 'local');

                                        if (!is_string($aPath) || empty($aPath) || $aPath === 'deleted_by_retention' || $aPath === 'deleted_by_system') {
                                            continue;
                                        }

                                        if (Storage::disk($aDisk)->exists($aPath)) {
                                            $aSize = (is_array($aEntry) && !empty($aEntry['size'])) ? (int)$aEntry['size'] : 0;
                                            if ($aSize === 0) {
                                                try {
                                                    $aSize = (int)Storage::disk($aDisk)->size($aPath);
                                                } catch (\Throwable $e) {}
                                            }

                                            if (!$dryRun) {
                                                Storage::disk($aDisk)->delete($aPath);
                                            }
                                            $freedBytes += $aSize;
                                            $deletedOrderFiles++;
                                            $addonModified = true;
                                        }

                                        if (is_array($addonFiles[$aIdx])) {
                                            $addonFiles[$aIdx]['path'] = 'deleted_by_retention';
                                        } else {
                                            $addonFiles[$aIdx] = 'deleted_by_retention';
                                        }
                                    }

                                    if ($addonModified && !$dryRun) {
                                        $addon->file_paths = $addonFiles;
                                        $addon->save();
                                    }
                                }
                            }
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
     * Strict Safety Gate: Verify if file path or its basename is used in ANY order_items or active carts.
     * Uses basename($path) to remain completely immune to JSON escaped slashes (\/).
     */
    protected function isFileProtected(string $path, int $excludeCartItemId = 0): bool
    {
        $fileBase = basename($path);

        // 1. NEVER delete files referenced in ANY active / non-archived order_item
        $inOrderItem = OrderItem::where(function($q) use ($path, $fileBase) {
                $q->where('uploaded_files', 'like', "%{$path}%")
                  ->orWhere('uploaded_files', 'like', "%{$fileBase}%");
            })
            ->where('file_status', '!=', 'cleaned_by_retention')
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', ['cancelled', 'expired']);
            })->exists();
        if ($inOrderItem) {
            return true;
        }

        // 2. NEVER delete files referenced in ANY active / non-archived order_item_addons
        $inOrderAddon = OrderItemAddon::where(function($q) use ($path, $fileBase) {
                $q->where('file_paths', 'like', "%{$path}%")
                  ->orWhere('file_paths', 'like', "%{$fileBase}%");
            })
            ->where('file_paths', 'not like', '%deleted_by_retention%')
            ->whereHas('orderItem.order', function ($q) {
                $q->whereNotIn('status', ['cancelled', 'expired']);
            })->exists();
        if ($inOrderAddon) {
            return true;
        }

        // 3. NEVER delete files referenced in recent active/pending carts (CartItem)
        $inActiveCart = CartItem::where('id', '!=', $excludeCartItemId)
            ->where(function($q) use ($path, $fileBase) {
                $q->where('uploaded_files', 'like', "%{$path}%")
                  ->orWhere('uploaded_files', 'like', "%{$fileBase}%");
            })
            ->whereHas('cart', function ($q) {
                $q->whereIn('status', ['active', 'pending'])
                  ->where('updated_at', '>=', Carbon::now()->subDays(14));
            })->exists();
        if ($inActiveCart) {
            return true;
        }

        // 4. NEVER delete files referenced in recent active/pending carts (CartItemAddon)
        $inActiveCartAddon = \App\Models\CartItemAddon::whereHas('cartItem.cart', function ($q) {
                $q->whereIn('status', ['active', 'pending'])
                  ->where('updated_at', '>=', Carbon::now()->subDays(14));
            })
            ->where(function ($q) use ($path, $fileBase) {
                $q->where('file_paths', 'like', "%{$path}%")
                  ->orWhere('file_paths', 'like', "%{$fileBase}%")
                  ->orWhere('temp_file_ids', 'like', "%{$path}%")
                  ->orWhere('temp_file_ids', 'like', "%{$fileBase}%");
            })->exists();
        if ($inActiveCartAddon) {
            return true;
        }

        return false;
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
