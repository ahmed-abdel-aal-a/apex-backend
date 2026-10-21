<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\StockReservation;
use App\Models\CouponUsage;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExpireStaleQuotesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'print:expire-stale-quotes {--grace-hours=24 : Extra grace window in hours after quote expiry}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire confirmed quotes that exceeded expiration date + grace window, release stock reservations and enable file cleanup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $graceHours = (int)$this->option('grace-hours');
        if ($graceHours <= 0) $graceHours = 24;

        $cutoff = Carbon::now()->subHours($graceHours);

        // Find orders whose quoted price expired before the grace cutoff and are still unpaid
        $staleOrders = Order::whereNotNull('price_expires_at')
            ->where('price_expires_at', '<', $cutoff)
            ->where('payment_status', 'unpaid')
            ->whereIn('status', ['pending_price_confirmation', 'needs_action', 'pending'])
            ->get();

        $count = 0;

        foreach ($staleOrders as $order) {
            try {
                DB::transaction(function () use ($order, &$count) {
                    $lockedOrder = Order::lockForUpdate()->find($order->id);
                    if (!$lockedOrder || $lockedOrder->payment_status !== 'unpaid') {
                        return;
                    }

                    $lockedOrder->status = 'expired';
                    $lockedOrder->admin_price_notes = trim(($lockedOrder->admin_price_notes ?? '') . ' [انتهت صلاحية عرض السعر والمهلة المحددة للسداد تم تفريغ الملفات والمخزون]');
                    $lockedOrder->save();

                    // 1. Release temporary stock reservations
                    StockReservation::where('order_id', $lockedOrder->id)->delete();

                    // 2. Restore coupon if used
                    $usage = CouponUsage::where('order_id', $lockedOrder->id)->first();
                    if ($usage) {
                        $coupon = Coupon::find($usage->coupon_id);
                        if ($coupon && $coupon->times_used > 0) {
                            $coupon->decrement('times_used');
                        }
                        $usage->delete();
                    }

                    $count++;
                    Log::info("ExpireStaleQuotesCommand: Order #{$lockedOrder->id} transitioned to expired.");
                });
            } catch (\Throwable $e) {
                Log::error("ExpireStaleQuotesCommand: Failed to expire Order #{$order->id}: " . $e->getMessage());
            }
        }

        $this->info("Expired stale quote orders: {$count}");
        return 0;
    }
}
