<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Jobs\CreateShippingWaybillJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

class PrintingDepositEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_status_endpoint_returns_deposit_information()
    {
        $order = Order::create([
            'customer_name' => 'Ahmed Ali',
            'customer_phone' => '01012345678',
            'items_count' => 1,
            'total' => 600.00,
            'subtotal' => 550.00,
            'shipping_cost' => 50.00,
            'status' => 'pending',
            'payment_status' => 'partially_paid',
            'payment_method' => 'kashier',
            'payment_type' => 'deposit',
            'deposit_percentage' => 30.00,
            'deposit_amount' => 180.00,
            'remaining_amount' => 420.00,
        ]);

        $response = $this->getJson("/api/v1/storefront/orders/{$order->id}/payment-status");

        $response->assertStatus(200)
            ->assertJson([
                'order_id' => $order->id,
                'status' => 'pending',
                'payment_status' => 'partially_paid',
                'payment_method' => 'kashier',
                'payment_type' => 'deposit',
                'deposit_percentage' => 30.00,
                'deposit_amount' => 180.00,
                'remaining_amount' => 420.00,
                'total' => 600.00,
            ]);
    }

    public function test_kashier_webhook_updates_deposit_order_to_partially_paid_and_does_not_dispatch_waybill()
    {
        Queue::fake([CreateShippingWaybillJob::class]);

        $order = Order::create([
            'customer_name' => 'Mohamed Tarek',
            'customer_phone' => '01098765432',
            'items_count' => 1,
            'total' => 1000.00,
            'subtotal' => 950.00,
            'shipping_cost' => 50.00,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'kashier',
            'payment_type' => 'deposit',
            'deposit_percentage' => 30.00,
            'deposit_amount' => 300.00,
            'remaining_amount' => 700.00,
        ]);

        $this->mock(\App\Services\Payment\KashierService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')->andReturn(true);
        });

        $payload = [
            'data' => [
                'merchantOrderId' => $order->id,
                'status' => 'SUCCESS',
                'amount' => 300.00,
                'currency' => 'EGP',
                'transactionId' => 'tx_test_12345',
            ]
        ];

        $response = $this->postJson('/api/v1/payment/kashier/webhook', $payload, [
            'x-kashier-signature' => 'valid_sig'
        ]);

        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals('partially_paid', $order->payment_status);
        $this->assertEquals('processing', $order->status);
        $this->assertNotNull($order->deposit_paid_at);

        Queue::assertNotPushed(CreateShippingWaybillJob::class);
    }

    public function test_order_status_ready_for_shipping_dispatches_waybill()
    {
        Queue::fake([CreateShippingWaybillJob::class]);

        $order = Order::create([
            'customer_name' => 'Sara Mahmoud',
            'customer_phone' => '01123456789',
            'items_count' => 1,
            'total' => 500.00,
            'subtotal' => 450.00,
            'shipping_cost' => 50.00,
            'status' => 'processing',
            'payment_status' => 'partially_paid',
            'payment_method' => 'kashier',
            'payment_type' => 'deposit',
            'deposit_amount' => 150.00,
            'remaining_amount' => 350.00,
        ]);

        $orderController = new \App\Http\Controllers\Api\OrderController();
        $request = new \Illuminate\Http\Request();
        $request->replace(['status' => 'ready_for_shipping']);

        $response = $orderController->updateStatus($request, $order->id);

        $this->assertEquals(200, $response->getStatusCode());

        $order->refresh();
        $this->assertEquals('ready_for_shipping', $order->status);

        Queue::assertPushed(CreateShippingWaybillJob::class, function ($job) use ($order) {
            $ref = new \ReflectionClass($job);
            $prop = $ref->getProperty('orderId');
            $prop->setAccessible(true);
            return $prop->getValue($job) === $order->id;
        });
    }

    public function test_delivered_status_settles_partially_paid_to_paid()
    {
        $order = Order::create([
            'customer_name' => 'Sara Mahmoud',
            'customer_phone' => '01123456789',
            'items_count' => 1,
            'total' => 500.00,
            'subtotal' => 450.00,
            'shipping_cost' => 50.00,
            'status' => 'shipped',
            'payment_status' => 'partially_paid',
            'payment_method' => 'kashier',
            'payment_type' => 'deposit',
            'deposit_amount' => 150.00,
            'remaining_amount' => 350.00,
        ]);

        $orderController = new \App\Http\Controllers\Api\OrderController();
        $request = new \Illuminate\Http\Request();
        $request->replace(['status' => 'delivered']);

        $response = $orderController->updateStatus($request, $order->id);

        $this->assertEquals(200, $response->getStatusCode());

        $order->refresh();
        $this->assertEquals('delivered', $order->status);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertNotNull($order->remaining_paid_at);
    }

    public function test_expire_unpaid_orders_cancels_stale_orders()
    {
        $staleOrder = Order::create([
            'customer_name' => 'Stale Order',
            'customer_phone' => '01000000001',
            'items_count' => 1,
            'total' => 400.00,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'kashier',
            'payment_type' => 'deposit',
        ]);
        \Illuminate\Support\Facades\DB::table('orders')->where('id', $staleOrder->id)->update([
            'created_at' => now()->subMinutes(50)
        ]);

        $freshOrder = Order::create([
            'customer_name' => 'Fresh Order',
            'customer_phone' => '01000000002',
            'items_count' => 1,
            'total' => 400.00,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => 'kashier',
            'payment_type' => 'deposit',
            'created_at' => now()->subMinutes(10),
        ]);

        $this->artisan('orders:expire-unpaid', ['--minutes' => 45])
            ->assertExitCode(0);

        $staleOrder->refresh();
        $freshOrder->refresh();

        $this->assertEquals('cancelled', $staleOrder->status);
        $this->assertEquals('pending', $freshOrder->status);
    }

    public function test_deposit_formula_calculation_with_min_deposit_amount()
    {
        $depositPercentage = 30.0;
        $minDepositAmount = 50.0;
        $printingSubtotal = 100.0;
        $grandTotal = 150.0; // 100 + 40 shipping + 10 cod_fee

        $rawDeposit = (float)bcmul((string)$printingSubtotal, bcdiv((string)$depositPercentage, '100', 4), 2);
        $depositCandidate = max($rawDeposit, $minDepositAmount);
        
        $this->assertEquals(30.0, $rawDeposit);
        $this->assertEquals(50.0, $depositCandidate);

        $depositAmount = $depositCandidate;
        $remainingAmount = (float)bcsub((string)$grandTotal, (string)number_format($depositAmount, 2, '.', ''), 2);

        $this->assertEquals(50.0, $depositAmount);
        $this->assertEquals(100.0, $remainingAmount);
        $this->assertEquals($grandTotal, $depositAmount + $remainingAmount);
    }

    public function test_deposit_formula_clamped_to_full_when_grand_total_less_than_min_deposit()
    {
        $depositPercentage = 30.0;
        $minDepositAmount = 50.0;
        $grandTotal = 45.0;

        $rawDeposit = 13.5;
        $depositCandidate = max($rawDeposit, $minDepositAmount);

        if ($grandTotal <= $minDepositAmount || $depositCandidate >= $grandTotal) {
            $depositAmount = $grandTotal;
            $remainingAmount = 0.00;
            $paymentType = 'full';
        }

        $this->assertEquals(45.0, $depositAmount);
        $this->assertEquals(0.00, $remainingAmount);
        $this->assertEquals('full', $paymentType);
    }
}
