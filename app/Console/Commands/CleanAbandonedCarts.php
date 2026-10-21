<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cart;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanAbandonedCarts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'carts:clean-abandoned';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete abandoned carts older than 30 days and their files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cutoff = Carbon::now()->subDays(30);

        // Only clean genuinely abandoned/lost carts that NEVER converted into orders
        $expiredCarts = Cart::with('items')
            ->whereIn('status', ['abandoned', 'lost'])
            ->whereDoesntHave('orders')
            ->where('updated_at', '<', $cutoff)
            ->get();

        $cartCount = 0;
        $fileCount = 0;

        foreach ($expiredCarts as $cart) {
            foreach ($cart->items as $item) {
                $files = is_array($item->uploaded_files) 
                    ? $item->uploaded_files 
                    : (json_decode($item->uploaded_files, true) ?: []);

                if (is_array($files)) {
                    foreach ($files as $fileData) {
                        $path = is_array($fileData) ? ($fileData['path'] ?? null) : $fileData;
                        if (is_string($path) && !empty($path)) {
                            // Strict Protection Gate: Never delete files referenced in any order item
                            $isReferencedInOrder = \App\Models\OrderItem::where('uploaded_files', 'like', "%{$path}%")->exists()
                                || \App\Models\OrderItemAddon::where('file_paths', 'like', "%{$path}%")->exists();

                            if (!$isReferencedInOrder && Storage::disk('local')->exists($path)) {
                                Storage::disk('local')->delete($path);
                                $fileCount++;
                            }
                        }
                    }
                }
            }
            $cart->delete();
            $cartCount++;
        }

        $this->info("Cleaned {$cartCount} abandoned carts and {$fileCount} related files.");
    }
}
