<?php

namespace App\Services\Storefront;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Setting;
use App\Models\ShippingZone;
use App\Models\StockReservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OrderPricingService
{
    /**
     * Recalculate complete financials for an order (Subtotal, Coupons, Shipping, Grand Total)
     * using strict BCMath precision (2 decimal places).
     */
    public static function recalculateFinancials(Order $order): Order
    {
        // 1. Calculate new Subtotal from order items and addons
        $items = $order->items()->with('addons')->get();
        $newSubtotal = '0.00';

        foreach ($items as $item) {
            $lineTotal = ($item->confirmed_line_total !== null)
                ? (string)$item->confirmed_line_total
                : (string)$item->total;

            $addonSum = '0.00';
            if ($item->addons && $item->addons->isNotEmpty()) {
                foreach ($item->addons as $addon) {
                    $addonSum = bcadd($addonSum, (string)$addon->total, 2);
                }
            }

            $itemFullTotal = bcadd($lineTotal, $addonSum, 2);
            $newSubtotal = bcadd($newSubtotal, $itemFullTotal, 2);
        }

        // 2. Validate and Recalculate Coupon if applied
        $discountAmount = '0.00';
        if ($order->coupon_id) {
            $coupon = Coupon::find($order->coupon_id);
            if ($coupon) {
                // Check minimum order amount requirement
                if ($coupon->min_order_amount && (float)$newSubtotal < (float)$coupon->min_order_amount) {
                    // Invalidate coupon due to subtotal falling below threshold
                    $discountAmount = '0.00';
                    $order->admin_price_notes = trim(($order->admin_price_notes ?? '') . " [تم إلغاء الكوبون لعدم استيفاء الحد الأدنى ({$coupon->min_order_amount} ج.م) بعد تحديث الأسعار]");
                    CouponUsage::where('order_id', $order->id)->delete();
                } else {
                    $calculated = $coupon->calculateDiscount((float)$newSubtotal);
                    $discountAmount = number_format($calculated, 2, '.', '');
                    CouponUsage::where('order_id', $order->id)->update([
                        'discount_amount' => $discountAmount,
                    ]);
                }
            }
        }

        // 3. Net Subtotal after discount
        $netSubtotal = bcsub($newSubtotal, $discountAmount, 2);
        if ((float)$netSubtotal < 0) {
            $netSubtotal = '0.00';
        }

        // 4. Calculate Shipping, COD, and Tax via dedicated ShippingCalculationService
        $settings = Setting::getAllSettings();
        $shippingService = app(\App\Services\Shipping\ShippingCalculationService::class);
        $calcResult = $shippingService->calculate(
            subtotal: (string)$newSubtotal,
            discountAmount: (string)$discountAmount,
            zoneId: (int)($order->zone_id ?: 1),
            shippingMethod: (string)($order->shipping_method ?: 'standard'),
            paymentMethod: (string)($order->payment_method ?: 'online'),
            taxAmount: (string)($order->tax_amount ?? '0.00')
        );

        $newShippingCost = $calcResult['final_shipping_cost'];
        $grandTotal = $calcResult['grand_total'];
        $codFee = $calcResult['cod_fee'];
        $taxAmount = $calcResult['tax_amount'];

        // 5. Deposit and Remaining recalculation if order is a deposit order
        $newDepositAmount = $order->deposit_amount;
        $newRemainingAmount = $order->remaining_amount;

        if ($order->payment_type === 'deposit') {
            $depositPercentage = (float)($order->deposit_percentage ?: ($settings['deposit_percentage'] ?? 30.0));
            $minDepositAmount = (float)($settings['min_deposit_amount'] ?? 50.0);
            $depositCalcBasis = $settings['deposit_calculation_basis'] ?? 'printing_only';

            // Calculate printing-only subtotal if basis is printing_only
            $printingSubtotal = '0.00';
            if ($depositCalcBasis === 'printing_only') {
                foreach ($items as $item) {
                    if ($item->item_type === 'printing') {
                        $pLineTotal = ($item->confirmed_line_total !== null) ? (string)$item->confirmed_line_total : (string)$item->total;
                        $printingSubtotal = bcadd($printingSubtotal, $pLineTotal, 2);
                    }
                }
            }

            $basis = ($depositCalcBasis === 'all_items') ? (float)$calcResult['net_items_total'] : (float)$printingSubtotal;
            $rawDeposit = (float)bcmul((string)$basis, bcdiv((string)$depositPercentage, '100', 4), 2);
            $depositCandidate = max($rawDeposit, $minDepositAmount);

            if ((float)$grandTotal <= $minDepositAmount || $depositCandidate >= (float)$grandTotal) {
                $newDepositAmount = (float)$grandTotal;
                $newRemainingAmount = 0.00;
            } else {
                $newDepositAmount = $depositCandidate;
                $newRemainingAmount = (float)bcsub((string)$grandTotal, (string)number_format($newDepositAmount, 2, '.', ''), 2);
            }
        }

        // 6. Save updated values
        $order->subtotal = $newSubtotal;
        $order->discount_amount = $discountAmount;
        $order->shipping_cost = $newShippingCost;
        $order->cod_fee = $codFee;
        $order->tax_amount = $taxAmount;
        $order->total = $grandTotal;
        if ($order->payment_type === 'deposit') {
            $order->deposit_amount = $newDepositAmount;
            $order->remaining_amount = $newRemainingAmount;
        }
        $order->save();

        return $order;
    }

    /**
     * Confirm / Adjust a specific order item's total price by admin,
     * recording audit fields and recalculating order totals.
     */
    public static function confirmItemPrice(
        OrderItem $item, 
        float $confirmedLineTotal, 
        ?string $adminNotes = null, 
        ?int $adminUserId = null
    ): OrderItem {
        $oldTotal = (string)$item->total;
        $newTotalStr = number_format($confirmedLineTotal, 2, '.', '');
        $difference = bcsub($newTotalStr, $oldTotal, 2);

        $item->original_line_total = $item->original_line_total ?? $item->total;
        $item->confirmed_line_total = $newTotalStr;
        $item->total = $newTotalStr;
        $item->price_at_purchase = bcdiv($newTotalStr, (string)max(1, (int)$item->quantity), 2);
        $item->line_difference = $difference;
        $item->price_confirmation_status = 'confirmed';
        if ($adminNotes) {
            $item->admin_notes = $adminNotes;
        }
        $item->save();

        // Recalculate parent order financials
        self::recalculateFinancials($item->order);

        // If order has an active price expiration, ensure stock reservations are kept safe
        if ($item->order->price_expires_at) {
            self::extendStockReservations($item->order, $item->order->price_expires_at->copy()->addMinutes(30));
        }

        return $item;
    }

    /**
     * Generate secure one-time payment token and set confirmation & expiration timestamps.
     */
    public static function issuePriceConfirmation(Order $order, int $hoursValid = 24, ?string $adminNotes = null): string
    {
        $token = bin2hex(random_bytes(24));
        $now = Carbon::now();
        $expiresAt = $now->copy()->addHours($hoursValid);

        $order->payment_token = $token;
        $order->price_confirmed_at = $now;
        $order->price_expires_at = $expiresAt;
        $order->payment_token_expires_at = $expiresAt;
        if ($adminNotes) {
            $order->admin_price_notes = $adminNotes;
        }
        $order->save();

        // Extend any stock reservations for this order to match price expiration + 30 min grace period
        self::extendStockReservations($order, $expiresAt->copy()->addMinutes(30));

        return $token;
    }

    /**
     * Extend stock reservations tied to an order.
     */
    public static function extendStockReservations(Order $order, Carbon $newExpiry): void
    {
        StockReservation::where('order_id', $order->id)
            ->update(['expires_at' => $newExpiry]);
    }
}
