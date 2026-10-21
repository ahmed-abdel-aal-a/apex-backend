<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReleasePendingOrders extends Command
{
    protected $signature = 'orders:release-pending';
    protected $description = 'Release inventory for pending unpaid visa orders older than 30 minutes';

    public function handle()
    {
        $cutoffTime = Carbon::now()->subMinutes(30);

        // Find orders that need to be cancelled
        $expiredOrders = Order::where('payment_method', 'visa')
            ->where('payment_status', 'unpaid')
            ->where('status', 'pending')
            ->where('created_at', '<', $cutoffTime)
            ->get();

        $count = 0;

        foreach ($expiredOrders as $order) {
            try {
                DB::beginTransaction();

                // Lock the order to prevent concurrent webhook updates
                $lockedOrder = Order::lockForUpdate()->find($order->id);

                if ($lockedOrder->payment_status === 'paid' || $lockedOrder->status !== 'pending') {
                    // Paymob webhook arrived exactly now, skip cancellation
                    DB::rollBack();
                    continue;
                }

                $lockedOrder->status = 'cancelled';
                $lockedOrder->payment_status = 'failed';
                $lockedOrder->save();

                // Release reservations
                \App\Models\StockReservation::where('order_id', $lockedOrder->id)->delete();

                DB::commit();
                $count++;
                Log::info("Released inventory for expired order {$order->id}");

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed to release inventory for order {$order->id}: " . $e->getMessage());
            }
        }

        $this->info("Successfully released inventory for {$count} expired orders.");
    }
}
