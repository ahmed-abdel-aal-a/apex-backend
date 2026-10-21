<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Setting;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OrderPaymentTokenController extends Controller
{
    /**
     * Fetch order details by secure payment token.
     */
    public function showByToken(Request $request, $token)
    {
        $order = Order::with(['items.addons', 'items.printingType'])
            ->where('payment_token', $token)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'عذراً، رابط الطلب غير صحيح أو غير متوفر.'], 404);
        }

        $now = Carbon::now();
        $isExpired = $order->price_expires_at && $now->gt($order->price_expires_at);
        $graceCutoff = $order->price_expires_at ? $order->price_expires_at->copy()->addHours(24) : null;
        $canRequestReprice = $isExpired && $graceCutoff && $now->lt($graceCutoff) && $order->status !== 'expired';

        $settings = Setting::getAllSettings();

        return response()->json([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'shipping_address' => $order->shipping_address,
                'subtotal' => (float)$order->subtotal,
                'shipping_cost' => (float)$order->shipping_cost,
                'shipping_method' => $order->shipping_method ?? 'standard',
                'discount_amount' => (float)$order->discount_amount,
                'total' => (float)$order->total,
                'payment_type' => $order->payment_type,
                'deposit_amount' => (float)$order->deposit_amount,
                'remaining_amount' => (float)$order->remaining_amount,
                'deposit_percentage' => (float)$order->deposit_percentage,
                'required_payment_amount' => ($order->payment_type === 'deposit' && $order->payment_status === 'partially_paid')
                    ? (float)$order->remaining_amount
                    : (($order->payment_type === 'deposit' && (float)$order->deposit_amount > 0) ? (float)$order->deposit_amount : (float)$order->total),
                'admin_price_notes' => $order->admin_price_notes,
                'price_confirmed_at' => $order->price_confirmed_at?->toIso8601String(),
                'price_expires_at' => $order->price_expires_at?->toIso8601String(),
                'is_expired' => $isExpired,
                'can_request_reprice' => $canRequestReprice,
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'custom_product_name' => $item->custom_product_name,
                        'item_type' => $item->item_type,
                        'quantity' => $item->quantity,
                        'price_at_purchase' => (float)$item->price_at_purchase,
                        'total' => (float)$item->total,
                        'original_line_total' => $item->original_line_total ? (float)$item->original_line_total : (float)$item->total,
                        'confirmed_line_total' => $item->confirmed_line_total ? (float)$item->confirmed_line_total : (float)$item->total,
                        'line_difference' => $item->line_difference ? (float)$item->line_difference : 0,
                        'price_confirmation_status' => $item->price_confirmation_status,
                        'admin_notes' => $item->admin_notes,
                        'printing_options' => $item->printing_options,
                        'addons' => $item->addons,
                    ];
                }),
            ],
            'direct_payment_info' => [
                'instapay_address' => $settings['instapay_address'] ?? $settings['instapay_username'] ?? 'apexstore@instapay',
                'vodafone_cash_number' => $settings['vodafone_cash_number'] ?? $settings['wallet_number'] ?? '01000000000',
                'whatsapp_support' => $settings['whatsapp_number'] ?? $settings['support_phone'] ?? '01000000000',
            ]
        ]);
    }

    /**
     * Initiate online payment session and lock order for 20 minutes against admin edits.
     */
    public function initiatePayment(Request $request, $token)
    {
        $order = Order::where('payment_token', $token)->first();

        if (!$order) {
            return response()->json(['message' => 'طلب غير موجود.'], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'هذا الطلب تم سداده بالفعل بنجاح.'], 400);
        }

        if ($order->payment_type === 'deposit' && $order->payment_status === 'partially_paid' && (float)$order->remaining_amount <= 0) {
            return response()->json(['message' => 'تم سداد كامل المبلغ المتبقي لهذا الطلب مسبقاً.'], 400);
        }

        if ($order->price_expires_at && Carbon::now()->gt($order->price_expires_at)) {
            return response()->json(['message' => 'عذراً، انتهت صلاحية عرض السعر لمرور المهلة المحددة.'], 422);
        }

        // Lock order session against admin price modifications for 20 minutes
        $order->update([
            'payment_session_expires_at' => Carbon::now()->addMinutes(20)
        ]);

        $successUrl = $request->input('success_url', url('/checkout/success'));
        $failureUrl = $request->input('failure_url', url('/checkout/error'));

        try {
            $gatewayCode = PaymentGatewayManager::getActiveGatewayCode();
            $paymentService = PaymentGatewayManager::resolveService($gatewayCode);

            $order->update(['payment_method' => $gatewayCode]);
            $paymentUrl = $paymentService->generateCheckoutUrl($order, $successUrl, $failureUrl);

            return response()->json([
                'message' => 'Payment initiated successfully',
                'payment_url' => $paymentUrl
            ]);
        } catch (\Throwable $e) {
            Log::error("OrderPaymentTokenController initiatePayment failed: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage() ?: 'فشل بدء جلسة الدفع الإلكتروني.'], 500);
        }
    }

    /**
     * Request re-pricing for an expired quote within grace period.
     */
    public function requestReprice(Request $request, $token)
    {
        $order = Order::with('items')->where('payment_token', $token)->first();

        if (!$order) {
            return response()->json(['message' => 'طلب غير موجود.'], 404);
        }

        if ($order->status === 'expired') {
            return response()->json(['message' => 'تم تفريغ ملفات هذا الطلب لانقضاء المهلة النهائية. يرجى تقديم طلب جديد.'], 400);
        }

        // Re-check inventory for standard products
        foreach ($order->items as $item) {
            if ($item->item_type === 'standard' && $item->product_id) {
                $product = \App\Models\Product::find($item->product_id);
                if ($product && $product->track_inventory && $product->quantity < $item->quantity) {
                    return response()->json([
                        'message' => "عذراً، نفدت كمية المنتج ({$product->name}) المطلوبة بالمخزن أثناء فترة الانتظار."
                    ], 422);
                }
            }
        }

        $order->status = 'pending_price_confirmation';
        $order->payment_session_expires_at = null;
        $order->admin_price_notes = trim(($order->admin_price_notes ?? '') . ' [طلب العميل تجديد تسعيرة اليوم]');
        $order->save();

        return response()->json([
            'message' => 'تم إرسال طلب تجديد التسعيرة بنجاح إلى فريق المطبعة، وسيتم التواصل معك بالرابط المحدث قريباً.'
        ]);
    }
}
