<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Jobs\CreateShippingWaybillJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymobWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. HMAC Validation
        if (!$this->verifyHmac($request)) {
            Log::warning('Paymob Webhook HMAC Validation Failed.', $request->all());
            return response()->json(['message' => 'Invalid HMAC'], 403);
        }

        $type = $request->input('type');
        $obj = $request->input('obj');

        if ($type === 'TRANSACTION' && isset($obj['success'])) {
            $orderId = $obj['order']['merchant_order_id'] ?? null;

            if (!$orderId) {
                return response()->json(['message' => 'Missing Order ID'], 400);
            }

            try {
                DB::beginTransaction();

                // 2. Lock the Order to prevent Race Conditions & Idempotency Check
                $order = Order::lockForUpdate()->find($orderId);

                if (!$order) {
                    DB::rollBack();
                    return response()->json(['message' => 'Order not found'], 404);
                }

                // 3. Webhook Idempotency & Cancellation Check
                // If payment is already paid, we safely ignore the webhook duplicate.
                if ($order->payment_status === 'paid') {
                    DB::rollBack();
                    return response()->json(['message' => 'Webhook already processed']);
                }

                // If the order was cancelled by the ReleasePendingOrders job (or manually),
                // we should NOT resurrect it. It means the payment arrived too late.
                // We should flag it for manual review or refund, but absolutely do NOT dispatch shipping.
                if ($order->status === 'cancelled') {
                    DB::rollBack();
                    Log::critical("Paymob Webhook received for CANCELLED Order {$order->id}. Manual refund/review required.");
                    return response()->json(['message' => 'Order was cancelled. Payment arrived too late.']);
                }

                // 4. Update Statuses
                if ($obj['success'] === true) {
                    // Syncing both payment_status and status
                    $order->payment_status = 'paid';
                    $order->status = 'processing';
                    $order->save();

                    $order->load('items.addons');

                    // خصم المخزون الفعلي بعد نجاح الدفع الإلكتروني
                    $affectedProducts = [];
                    $variantDeductions = [];
                    $productDeductions = [];

                    foreach ($order->items as $orderItem) {
                        if ($orderItem->item_type === 'standard') {
                            if ($orderItem->variant_id) {
                                $variantDeductions[$orderItem->variant_id] = ($variantDeductions[$orderItem->variant_id] ?? 0) + $orderItem->quantity;
                            } elseif ($orderItem->product_id) {
                                $productDeductions[$orderItem->product_id] = ($productDeductions[$orderItem->product_id] ?? 0) + $orderItem->quantity;
                            }
                        }
                    }

                    if (!empty($variantDeductions)) {
                        $vIds = array_keys($variantDeductions);
                        sort($vIds);
                        $variants = \App\Models\ProductVariant::with('product')
                            ->whereIn('id', $vIds)
                            ->lockForUpdate()
                            ->get();

                        foreach ($variants as $variant) {
                            $qtyToDeduct = $variantDeductions[$variant->id];
                            if ($variant->track_inventory) {
                                $variant->decrement('quantity', min($variant->quantity, $qtyToDeduct));
                            }
                            if ($variant->product) {
                                $variant->product->syncWithVariants();
                                $affectedProducts[$variant->product->id] = $variant->product;
                            }
                        }
                    }

                    if (!empty($productDeductions)) {
                        $pIds = array_keys($productDeductions);
                        sort($pIds);
                        $products = \App\Models\Product::whereIn('id', $pIds)
                            ->lockForUpdate()
                            ->get();

                        foreach ($products as $prod) {
                            $qtyToDeduct = $productDeductions[$prod->id];
                            if ($prod->track_inventory) {
                                $prod->decrement('quantity', min($prod->quantity, $qtyToDeduct));
                            }
                            $affectedProducts[$prod->id] = $prod;
                        }
                    }

                    foreach ($affectedProducts as $prod) {
                        $prod->clearStorefrontCache();
                    }

                    // تجميع كميات الإضافات المتتبعة للمخزون وخصمها مع منع Deadlock والنزول للسالب
                    $totalsByAddonId = [];
                    foreach ($order->items as $orderItem) {
                        if ($orderItem->item_type === 'standard' && $orderItem->relationLoaded('addons')) {
                            foreach ($orderItem->addons as $itemAddon) {
                                if ($itemAddon->product_addon_id) {
                                    $totalsByAddonId[$itemAddon->product_addon_id] = ($totalsByAddonId[$itemAddon->product_addon_id] ?? 0) + $itemAddon->quantity;
                                }
                            }
                        }
                    }

                    if (!empty($totalsByAddonId)) {
                        $addonIds = array_keys($totalsByAddonId);
                        sort($addonIds);
                        $trackedAddons = \App\Models\ProductAddon::whereIn('id', $addonIds)
                            ->where('track_inventory', true)
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get();

                        foreach ($trackedAddons as $tAddon) {
                            $requiredQty = $totalsByAddonId[$tAddon->id];
                            $tAddon->decrement('quantity', min($tAddon->quantity, $requiredQty));
                        }
                    }

                    // Delete reservations
                    \App\Models\StockReservation::where('order_id', $order->id)->delete();

                    // 5. Dispatch Shipping Job
                    CreateShippingWaybillJob::dispatch($order->id)->afterCommit();

                    Log::info("Paymob Transaction SUCCESS for Order: {$order->id}");
                } else {
                    // Payment Failed
                    $order->payment_status = 'failed';
                    $order->status = 'cancelled';
                    $order->save();
                    
                    // Release Inventory Reservations
                    \App\Models\StockReservation::where('order_id', $order->id)->delete();
                    
                    Log::info("Paymob Transaction FAILED for Order: {$order->id}. Inventory reservations cleared.");
                }

                DB::commit();

                return response()->json(['message' => 'Webhook processed successfully']);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Paymob Webhook Error: ' . $e->getMessage());
                return response()->json(['message' => 'Internal Error'], 500);
            }
        }

        return response()->json(['message' => 'Unhandled Type']);
    }

    private function verifyHmac(Request $request)
    {
        // Concatenate keys based on Paymob Documentation
        $hmacKeys = [
            'amount_cents', 'created_at', 'currency', 'error_occured', 
            'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure', 
            'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 
            'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan', 
            'source_data.sub_type', 'source_data.type', 'success'
        ];

        $obj = $request->input('obj');
        if (!$obj) return false;

        $concatenatedString = '';
        foreach ($hmacKeys as $key) {
            $value = data_get($obj, $key);
            // Handle boolean strings as Paymob returns them
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $concatenatedString .= $value;
        }

        $secret = config('services.paymob.hmac_secret');
        if (!$secret) {
            // For dev environment if not set, let it pass or fail depending on strictness
            // Best practice is to fail, but to avoid blocking we log it.
            Log::error('Paymob HMAC Secret is not configured.');
            return false;
        }

        $hashed = hash_hmac('sha512', $concatenatedString, $secret);

        return hash_equals($hashed, $request->query('hmac'));
    }
}
