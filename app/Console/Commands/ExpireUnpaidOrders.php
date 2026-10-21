<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireUnpaidOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:expire-unpaid {--minutes=45 : Expiration threshold in minutes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire unpaid online and deposit orders and release locked inventory and coupons';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $minutes = (int)$this->option('minutes');
        $expiryTime = now()->subMinutes($minutes > 0 ? $minutes : 45)->toDateTimeString();

        // Find unpaid online or deposit orders older than threshold
        // Strictly exclude COD, manual transfer methods awaiting verification, and smart pricing orders
        $manualMethods = ['cod', 'instapay', 'vodafone_cash', 'bank_transfer'];
        $orders = Order::whereNotIn('payment_method', $manualMethods)
            ->where('payment_status', 'unpaid')
            ->whereIn('status', ['needs_action', 'pending'])
            ->whereNotIn('status', ['pending_price_confirmation', 'cancelled', 'refunded', 'completed'])
            ->where(function ($q) {
                $q->whereNull('price_expires_at')
                  ->orWhere('price_expires_at', '<', now());
            })
            ->where(function ($q) {
                $q->whereNull('payment_token_expires_at')
                  ->orWhere('payment_token_expires_at', '<', now());
            })
            ->where('created_at', '<', $expiryTime)
            ->get();

        $count = 0;

        foreach ($orders as $order) {
            try {
                DB::transaction(function () use ($order, $minutes) {
                    $order = Order::lockForUpdate()->find($order->id);
                    
                    if ($order->payment_status !== 'unpaid') {
                        return; // Prevent race conditions if webhook just arrived
                    }

                    $order->status = 'cancelled';
                    $order->admin_price_notes = ($order->admin_price_notes ? $order->admin_price_notes . ' | ' : '') . "تم الإلغاء تلقائياً لعدم سداد المبلغ/العربون إلكترونياً خلال المهلة المحددة ({$minutes} دقيقة).";
                    $order->save();

                    // Restore coupon usage if coupon was applied
                    if ($order->coupon_id) {
                        $deleted = \App\Models\CouponUsage::where('order_id', $order->id)->delete();
                        if ($deleted > 0) {
                            \App\Models\Coupon::where('id', $order->coupon_id)
                                ->where('times_used', '>', 0)
                                ->decrement('times_used');
                        }
                    }

                    // Find and delete the stock reservations linked to this order
                    StockReservation::where('order_id', $order->id)->get()->each->delete();
                });
                
                $this->info("Expired Order ID: {$order->id}");
                $count++;
            } catch (\Exception $e) {
                Log::error("Failed to expire order {$order->id}: " . $e->getMessage());
                $this->error("Failed to expire order {$order->id}");
            }
        }

        $this->info("Completed. Expired {$count} orders.");
    }
}
