<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\Payment\KashierService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\CreateShippingWaybillJob;

class KashierWebhookController extends Controller
{
    protected $kashierService;

    public function __construct(KashierService $kashierService)
    {
        $this->kashierService = $kashierService;
    }

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('x-kashier-signature'); // Verify Kashier actual header name from docs

        if (!$this->kashierService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Kashier Webhook Signature Mismatch');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $data = json_decode($payload, true);
        $orderId = $data['data']['merchantOrderId'] ?? null;
        $status = $data['data']['status'] ?? null;
        $amount = $data['data']['amount'] ?? null;
        $currency = $data['data']['currency'] ?? null;

        if (!$orderId) {
            return response()->json(['error' => 'Missing Order ID'], 400);
        }

        $transactionId = $data['data']['transactionId'] ?? 'unknown';
        $lockKey = "webhook_processing_{$transactionId}";
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            return response()->json(['message' => 'Concurrent webhook processing'], 409);
        }

        try {
            DB::beginTransaction();

            // Webhook Idempotency & Race Condition handling via Lock
            $order = Order::lockForUpdate()->find($orderId);

            if (!$order) {
                DB::rollBack();
                $lock->release();
                return response()->json(['error' => 'Order not found'], 404);
            }

            // Check if already paid or partially paid (Idempotency)
            if ($order->payment_status === 'paid' || ($order->payment_type === 'deposit' && $order->payment_status === 'partially_paid')) {
                DB::rollBack();
                $lock->release();
                // Return 200 OK to stop Kashier from retrying
                return response()->json(['message' => 'Order already processed']);
            }

            if ($status === 'SUCCESS') {
                // Security: Amount and Currency Matching
                $webhookAmount = (float) $amount;
                if ($order->payment_type === 'deposit') {
                    if ($order->payment_status === 'partially_paid') {
                        $expectedAmount = (float)$order->remaining_amount;
                    } elseif ((float)$order->deposit_amount > 0) {
                        $expectedAmount = (float)$order->deposit_amount;
                    } else {
                        $expectedAmount = (float)$order->total;
                    }
                } else {
                    $expectedAmount = (float)$order->total;
                }

                $isAmountValid = abs($webhookAmount - $expectedAmount) < 0.01;
                $isCurrencyValid = strtoupper($currency) === 'EGP';

                if (!$isAmountValid || !$isCurrencyValid) {
                    Log::alert("Security Alert: Amount or currency mismatch for order {$order->id}. Expected: {$expectedAmount} EGP. Received: {$webhookAmount} {$currency}");
                    $order->payment_status = 'flagged_fraud';
                    $order->save();
                    DB::commit();
                    $lock->release();
                    return response()->json(['message' => 'Payment flagged for manual review']);
                }

                if ($order->payment_type === 'deposit') {
                    if ($order->payment_status === 'partially_paid') {
                        $order->payment_status = 'paid';
                        $order->remaining_paid_at = now();
                    } else {
                        $order->payment_status = 'partially_paid';
                        $order->deposit_paid_at = now();
                    }
                } else {
                    $order->payment_status = 'paid';
                }
                $order->status = 'processing';
                $order->save();

                // خصم المخزون الفعلي بعد نجاح الدفع الإلكتروني
                app(\App\Services\Inventory\InventoryService::class)->deductOrderInventory($order);

                // حذف الحجوزات المؤقتة بعد تثبيت الخصم الفعلي
                \App\Models\StockReservation::where('order_id', $order->id)->delete();

                DB::commit();
                $lock->release();

                // Dispatch shipping job after commit only for standard retail full-paid orders
                // Printing and deposit orders require production and only get waybills when ready_for_shipping
                $hasPrintingItems = $order->items->contains(fn($item) => $item->item_type === 'printing');
                if (!$hasPrintingItems && $order->payment_type !== 'deposit') {
                    CreateShippingWaybillJob::dispatch($order->id);
                }

                return response()->json(['message' => 'Webhook processed successfully']);
            }

            if ($status === 'FAILED') {
                $order->payment_status = 'failed';
                $order->save();
                DB::commit();
                $lock->release();

                return response()->json(['message' => 'Payment failed registered']);
            }

            DB::rollBack();
            $lock->release();
            return response()->json(['message' => 'Unhandled status'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($lock)) $lock->release();
            Log::error('Kashier Webhook Error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
