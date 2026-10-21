<?php

namespace App\Http\Controllers\Api\Shipping;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\Shipping\ShippingFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ShippingWebhookController extends Controller
{
    protected $shippingFactory;

    public function __construct(ShippingFactory $shippingFactory)
    {
        $this->shippingFactory = $shippingFactory;
    }

    public function handle(Request $request, string $providerName)
    {
        try {
            // Find provider (this would normally map to the specific provider class)
            // For now, we simulate resolving it.
            // $provider = $this->shippingFactory->resolveProviderByName($providerName);
            // $parsedData = $provider->parseWebhook($request->all());
            
            // Dummy implementation for Phase 1
            $parsedData = [
                'tracking_number' => $request->input('tracking_number'),
                'status' => $request->input('status'),
                'timestamp' => Carbon::parse($request->input('timestamp') ?? now()),
            ];

            if (!$parsedData['tracking_number'] || !$parsedData['status']) {
                return response()->json(['message' => 'Invalid payload'], 400);
            }

            $order = Order::where('tracking_number', $parsedData['tracking_number'])->first();
            
            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            // Timestamp-Based Idempotency Check (Strict: Less Than or Equal)
            if ($order->latest_shipping_update_at && $parsedData['timestamp']->lte($order->latest_shipping_update_at)) {
                Log::info("Ignored out-of-order or duplicate webhook for order {$order->id}");
                return response()->json(['message' => 'Ignored out-of-order or duplicate webhook'], 200);
            }

            // Map external courier status to valid internal system statuses
            $rawStatus = strtoupper(trim((string)$parsedData['status']));
            $statusMapping = [
                'DELIVERED' => 'delivered',
                'DELIVERED_COLLECTED' => 'delivered',
                'COMPLETED' => 'delivered',
                'SUCCESS' => 'delivered',
                'RETURNED' => 'returned',
                'RETURNED_TO_ORIGIN' => 'returned',
                'FAILED_DELIVERY' => 'returned',
                'CANCELLED' => 'cancelled',
                'CANCELED' => 'cancelled',
                'IN_TRANSIT' => 'shipped',
                'OUT_FOR_DELIVERY' => 'shipped',
                'SHIPPED' => 'shipped',
                'PICKED_UP' => 'shipped',
                'PACKAGE_RECEIVED' => 'shipped',
            ];

            $systemStatus = $statusMapping[$rawStatus] ?? null;
            if (!$systemStatus) {
                Log::warning("Unknown external shipping status '{$rawStatus}' for order {$order->id}");
                return response()->json(['message' => 'Status acknowledged but not actionable'], 200);
            }

            // Update order status and settle remaining financial collection upon delivery
            $updates = [
                'status' => $systemStatus,
                'latest_shipping_update_at' => $parsedData['timestamp']
            ];

            if ($systemStatus === 'delivered') {
                if ($order->payment_status === 'partially_paid' || ($order->payment_method === 'cod' && $order->payment_status !== 'paid')) {
                    $updates['payment_status'] = 'paid';
                    $updates['remaining_paid_at'] = now();
                }
            }

            $order->update($updates);

            return response()->json(['message' => 'Status updated successfully']);

        } catch (\Exception $e) {
            Log::error("Shipping Webhook Error ($providerName): " . $e->getMessage());
            return response()->json(['message' => 'Internal server error'], 500);
        }
    }
}
