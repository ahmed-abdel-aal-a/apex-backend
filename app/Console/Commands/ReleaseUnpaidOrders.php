<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ReleaseUnpaidOrders extends Command
{
    protected $signature = 'orders:release-unpaid';
    protected $description = 'Cancel online orders that have not been paid after 60 minutes to release inventory.';

    public function handle()
    {
        $expirationTime = now()->subMinutes(60);

        // Fetch pending online orders older than 60 mins
        $unpaidOrders = Order::where('payment_status', 'unpaid')
            ->where('payment_method', '!=', 'cod') // all online gateways
            ->where('created_at', '<', $expirationTime)
            ->where('status', 'pending')
            ->get();

        $count = 0;
        foreach ($unpaidOrders as $order) {
            DB::transaction(function () use ($order) {
                // Mark order as expired
                $order->update([
                    'status' => 'cancelled',
                    'notes' => 'تم الإلغاء تلقائياً لعدم الدفع الإلكتروني خلال الوقت المحدد.'
                ]);

                // Release stock reservations explicitly
                \App\Models\StockReservation::where('order_id', $order->id)->delete();
            });
            $count++;
        }

        if ($count > 0) {
            Log::info("Released and cancelled {$count} unpaid online orders.");
        }

        $this->info("Successfully cancelled {$count} unpaid orders.");
    }
}
