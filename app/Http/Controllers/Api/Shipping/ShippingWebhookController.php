<?php

namespace App\Http\Controllers\Api\Shipping;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\Shipping\ShippingFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShippingWebhookController extends Controller
{
    protected ShippingFactory $shippingFactory;

    public function __construct(ShippingFactory $shippingFactory)
    {
        $this->shippingFactory = $shippingFactory;
    }

    public function handle(Request $request, string $providerName)
    {
        // 1. فحص الصحة لطلبات GET (Bosta Health Check)
        if ($request->isMethod('GET')) {
            return response()->json([
                'status' => 'healthy',
                'provider' => $providerName,
                'message' => 'Apex Shipping Webhook Endpoint is operational',
                'timestamp' => now()->toIso8601String(),
            ], 200);
        }

        try {
            $provider = $this->shippingFactory->resolveProviderByName($providerName);
            $parsedData = $provider->parseWebhook($request->all());

            $trackingNumber = $parsedData['tracking_number'] ?? null;
            $providerId = $parsedData['shipping_provider_id'] ?? null;

            if (empty($trackingNumber) && empty($providerId)) {
                Log::warning("[ShippingWebhookController] Missing tracking number and provider ID in payload: " . json_encode($request->all()));
                return response()->json(['message' => 'Invalid payload: missing tracking reference'], 400);
            }

            return DB::transaction(function () use ($parsedData, $trackingNumber, $providerId, $providerName) {
                // البحث عن الطلب برقم التتبع أو معرّف بوسطة الداخلي مع القفل الذري لمنع الـ Race Condition
                $order = Order::lockForUpdate()
                    ->where(function ($q) use ($trackingNumber, $providerId) {
                        if (!empty($trackingNumber)) {
                            $q->where('tracking_number', $trackingNumber);
                        }
                        if (!empty($providerId)) {
                            $q->orWhere('shipping_provider_id', $providerId);
                        }
                    })
                    ->first();

                if (!$order) {
                    Log::warning("[ShippingWebhookController] Order not found for tracking '{$trackingNumber}' / providerId '{$providerId}'");
                    return response()->json(['message' => 'Order not found'], 404);
                }

                $eventTimestamp = $parsedData['timestamp'] ?? now();

                // 2. التحقق من التكرار والترتيب الزمني (Timestamp-Based Idempotency Guard)
                if ($order->latest_shipping_update_at && $eventTimestamp->lte($order->latest_shipping_update_at)) {
                    Log::info("[ShippingWebhookController] Ignored out-of-order or duplicate webhook for Order #{$order->id} (Event: {$eventTimestamp}, Last: {$order->latest_shipping_update_at})");
                    return response()->json(['message' => 'Ignored out-of-order or duplicate webhook'], 200);
                }

                $newStatus = $parsedData['status'];
                $rawStatus = $parsedData['raw_status'] ?? '';

                // 3. التحديث الدائم لحالة الشحن ووقت آخر تحديث في جدول orders
                $updates = [
                    'shipping_status' => $newStatus,
                    'latest_shipping_update_at' => $eventTimestamp,
                ];

                // 4. حماية الحالات النهائية (Terminal State Guard):
                // إذا كان الطلب في حالة نهائية (completed, delivered, cancelled, refunded) يُمنع رجوعه للوراء
                $terminalStatuses = ['completed', 'delivered', 'cancelled', 'refunded'];
                $isCurrentlyTerminal = in_array($order->status, $terminalStatuses);

                if (in_array($newStatus, ['completed', 'delivered'])) {
                    // تسليم نهائي
                    if (!$isCurrentlyTerminal) {
                        $updates['status'] = 'completed';
                    }

                    // 5. حماية تاريخ السداد الأصلي: لا نحدث إلا إذا لم يكن الطلب paid مسبقاً
                    if ($order->payment_status !== 'paid') {
                        $updates['payment_status'] = 'paid';
                        $updates['remaining_amount'] = 0.00;
                        $updates['remaining_paid_at'] = now();
                        Log::info("[ShippingWebhookController] Settled remaining balance to 0 for Order #{$order->id} upon delivery.");
                    }
                } elseif ($newStatus === 'returned') {
                    if (!$isCurrentlyTerminal) {
                        $updates['status'] = 'returned';
                    }
                } elseif ($newStatus === 'shipped') {
                    if (!$isCurrentlyTerminal) {
                        $updates['status'] = 'shipped';
                    }
                } elseif ($newStatus === 'cancelled') {
                    if (!$isCurrentlyTerminal) {
                        $updates['status'] = 'cancelled';
                    }
                }

                $order->update($updates);

                Log::info("[ShippingWebhookController] Order #{$order->id} updated via {$providerName} webhook to status '{$order->status}', shipping_status '{$newStatus}' (Raw: '{$rawStatus}')");

                return response()->json([
                    'status' => 'success',
                    'message' => 'Webhook processed successfully',
                    'order_id' => $order->id,
                    'system_status' => $order->status,
                    'shipping_status' => $order->shipping_status,
                ], 200);
            });
        } catch (Throwable $e) {
            Log::error("[ShippingWebhookController] Exception for provider '{$providerName}': " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error processing webhook: ' . $e->getMessage()
            ], 500);
        }
    }
}
