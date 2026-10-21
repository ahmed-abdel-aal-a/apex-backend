<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\PaymentGateway;
use App\Jobs\CreateShippingWaybillJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

class VolatilePricingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 10, 20, 12, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_customer_can_view_order_by_payment_token()
    {
        $order = Order::create([
            'customer_name' => 'Sara Ahmed',
            'customer_phone' => '01011112222',
            'shipping_address' => 'Cairo, Egypt',
            'items_count' => 1,
            'subtotal' => 500.00,
            'shipping_cost' => 50.00,
            'total' => 550.00,
            'status' => 'pending_price_confirmation',
            'payment_status' => 'unpaid',
            'payment_token' => 'test-secure-token-12345',
            'price_confirmed_at' => Carbon::now(),
            'price_expires_at' => Carbon::now()->addHours(24),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'name' => 'فلاير مقاس A4',
            'item_type' => 'printing',
            'quantity' => 1000,
            'price_at_purchase' => 0.50,
            'total' => 500.00,
            'original_line_total' => 450.00,
            'confirmed_line_total' => 500.00,
            'line_difference' => 50.00,
            'price_confirmation_status' => 'confirmed',
            'admin_notes' => 'زيادة 50 ج.م في سعر طن الورق الكوشيه 150 جرام',
        ]);

        $response = $this->getJson('/api/v1/storefront/orders/pay/test-secure-token-12345');

        $response->assertStatus(200)
            ->assertJson([
                'order' => [
                    'order_number' => $order->order_number,
                    'customer_name' => 'Sara Ahmed',
                    'total' => 550.00,
                    'status' => 'pending_price_confirmation',
                    'items' => [
                        [
                            'name' => 'فلاير مقاس A4',
                            'original_line_total' => 450.00,
                            'confirmed_line_total' => 500.00,
                            'line_difference' => 50.00,
                            'admin_notes' => 'زيادة 50 ج.م في سعر طن الورق الكوشيه 150 جرام',
                        ]
                    ]
                ],
                'direct_payment_info' => [
                    'instapay_address' => 'apexstore@instapay',
                ]
            ]);
    }

    public function test_admin_can_adjust_item_price_and_financials_recalculate_accurately_with_bcmath()
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $order = Order::create([
            'customer_name' => 'Mohamed Tarek',
            'customer_phone' => '01022223333',
            'shipping_address' => 'Giza, Egypt',
            'items_count' => 1,
            'subtotal' => 300.00,
            'shipping_cost' => 50.00,
            'total' => 350.00,
            'status' => 'pending_price_confirmation',
            'payment_status' => 'unpaid',
            'payment_token' => 'token-2002',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'name' => 'كروت شخصية فاخرة',
            'item_type' => 'printing',
            'quantity' => 1000,
            'price_at_purchase' => 0.30,
            'total' => 300.00,
            'original_line_total' => 300.00,
            'confirmed_line_total' => 300.00,
            'line_difference' => 0.00,
            'price_confirmation_status' => 'pending',
        ]);

        $response = $this->patchJson("/api/v1/admin/orders/{$order->id}/items/{$item->id}/confirm-price", [
            'confirmed_line_total' => 380.00,
            'admin_notes' => 'تحديث تسعيرة اليوم: سلوفان حراري مط',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'تم تحديث واعتماد سعر البند وإعادة احتساب الفاتورة بنجاح.',
            ]);

        $item->refresh();
        $this->assertEquals(380.00, (float)$item->confirmed_line_total);
        $this->assertEquals(80.00, (float)$item->line_difference);
        $this->assertEquals('confirmed', $item->price_confirmation_status);

        $order->refresh();
        $this->assertEquals(380.00, (float)$order->subtotal);
        $this->assertEquals(430.00, (float)$order->total); // 380 + 50 shipping
    }

    public function test_admin_issuing_price_confirmation_sets_expiry_and_extends_stock_reservation()
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $category = Category::create([
            'name' => 'General',
            'slug' => 'general-1',
        ]);

        $order = Order::create([
            'customer_name' => 'Omar Hassan',
            'customer_phone' => '01033334444',
            'shipping_address' => 'Alexandria, Egypt',
            'items_count' => 1,
            'subtotal' => 600.00,
            'shipping_cost' => 60.00,
            'total' => 660.00,
            'status' => 'pending_price_confirmation',
            'payment_status' => 'unpaid',
            'session_id' => 'session-test-3003',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'حامل كروت مكتبية',
            'slug' => 'card-holder',
            'price' => 50.00,
            'quantity' => 10,
            'track_inventory' => true,
        ]);

        $reservation = StockReservation::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'session_id' => 'session-test-3003',
            'quantity' => 2,
            'expires_at' => Carbon::now()->addHours(48),
        ]);

        $response = $this->postJson("/api/v1/admin/orders/{$order->id}/issue-price-confirmation", [
            'hours_valid' => 24,
            'admin_notes' => 'تم تثبيت سعر الخامات لـ 24 ساعة',
        ]);

        $response->assertStatus(200);

        $order->refresh();
        $this->assertNotNull($order->payment_token);
        $this->assertNotNull($order->price_confirmed_at);
        $this->assertNotNull($order->price_expires_at);

        // Verification of Grace Period: StockReservation extended to price_expires_at + 30 minutes
        $reservation->refresh();
        $expectedReservationExpiry = $order->price_expires_at->copy()->addMinutes(30);
        $this->assertEquals(
            $expectedReservationExpiry->toDateTimeString(),
            $reservation->expires_at->toDateTimeString()
        );
    }

    public function test_customer_initiating_payment_locks_session_against_admin_price_modifications()
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        PaymentGateway::create([
            'name' => 'Kashier',
            'code' => 'kashier',
            'is_active' => true,
            'is_configured' => true,
        ]);

        $this->mock(\App\Services\Payment\KashierService::class, function ($mock) {
            $mock->shouldReceive('generateCheckoutUrl')->andReturn('https://checkout.kashier.io/?order=123');
        });

        $order = Order::create([
            'customer_name' => 'Khaled Mostafa',
            'customer_phone' => '01044445555',
            'shipping_address' => 'Mansoura, Egypt',
            'items_count' => 1,
            'subtotal' => 400.00,
            'shipping_cost' => 40.00,
            'total' => 440.00,
            'status' => 'pending_price_confirmation',
            'payment_status' => 'unpaid',
            'payment_token' => 'token-lock-test-4004',
            'price_expires_at' => Carbon::now()->addHours(24),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'name' => 'أكياس ورقية مطبوعة',
            'item_type' => 'printing',
            'quantity' => 500,
            'price_at_purchase' => 0.80,
            'total' => 400.00,
            'original_line_total' => 400.00,
            'confirmed_line_total' => 400.00,
            'price_confirmation_status' => 'pending',
        ]);

        // Customer initiates payment
        $response = $this->postJson('/api/v1/storefront/orders/pay/token-lock-test-4004/initiate');
        $response->assertStatus(200);

        $order->refresh();
        $this->assertNotNull($order->payment_session_expires_at);
        $this->assertTrue($order->payment_session_expires_at->gt(Carbon::now()));

        // Admin attempts to modify price during active payment session -> must be rejected (422)
        $adminAttempt = $this->patchJson("/api/v1/admin/orders/{$order->id}/items/{$item->id}/confirm-price", [
            'confirmed_line_total' => 450.00,
        ]);

        $adminAttempt->assertStatus(422)
            ->assertJson([
                'message' => 'العميل متواجد في جلسة دفع نشطة حالياً. لا يمكن تعديل السعر حتى انتهاء الجلسة لمنع التضارب.',
            ]);
    }

    public function test_admin_can_confirm_manual_payment_for_instapay_and_dispatches_waybill()
    {
        Queue::fake([CreateShippingWaybillJob::class]);

        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $category = Category::create([
            'name' => 'General',
            'slug' => 'general-2',
        ]);

        $order = Order::create([
            'customer_name' => 'Youssef Nabil',
            'customer_phone' => '01055556666',
            'shipping_address' => 'Nasr City, Cairo',
            'items_count' => 1,
            'subtotal' => 700.00,
            'shipping_cost' => 50.00,
            'total' => 750.00,
            'status' => 'pending_price_confirmation',
            'payment_status' => 'unpaid',
            'payment_token' => 'token-manual-5005',
            'session_id' => 'session-manual-5005',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'أقلام مطبوعة',
            'slug' => 'printed-pens',
            'price' => 10.00,
            'quantity' => 100,
            'track_inventory' => true,
        ]);

        $reservation = StockReservation::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'session_id' => 'session-manual-5005',
            'quantity' => 50,
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $response = $this->postJson("/api/v1/admin/orders/{$order->id}/confirm-manual-payment", [
            'payment_method' => 'instapay',
            'transaction_reference' => 'IP-TRANS-987654',
            'admin_notes' => 'تم استلام 750 ج.م على حساب إنستاباي بنجاح',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'تم تأكيد استلام الدفع بنجاح وتحويل الطلب إلى قيد التنفيذ وإصدار بوليصة الشحن.',
            ]);

        $order->refresh();
        $this->assertEquals('processing', $order->status);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('instapay', $order->payment_method);

        // Temporary reservation deleted
        $this->assertDatabaseMissing('stock_reservations', ['id' => $reservation->id]);

        // Shipping waybill dispatched
        Queue::assertPushed(CreateShippingWaybillJob::class, function ($job) use ($order) {
            return $job->orderId === $order->id;
        });
    }

    public function test_customer_reprice_request_within_grace_period_resets_order_for_admin_review()
    {
        $order = Order::create([
            'customer_name' => 'Hany Mahmoud',
            'customer_phone' => '01066667777',
            'shipping_address' => 'Tanta, Egypt',
            'items_count' => 1,
            'subtotal' => 200.00,
            'shipping_cost' => 40.00,
            'total' => 240.00,
            'status' => 'pending_price_confirmation',
            'payment_status' => 'unpaid',
            'payment_token' => 'token-reprice-6006',
            'price_expires_at' => Carbon::now()->subHours(2), // expired 2 hours ago
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'name' => 'ملصقات دائرية',
            'item_type' => 'printing',
            'quantity' => 500,
            'price_at_purchase' => 0.40,
            'total' => 200.00,
            'price_confirmation_status' => 'confirmed',
        ]);

        $response = $this->postJson('/api/v1/storefront/orders/pay/token-reprice-6006/request-reprice');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'تم إرسال طلب تجديد التسعيرة بنجاح إلى فريق المطبعة، وسيتم التواصل معك بالرابط المحدث قريباً.',
            ]);

        $order->refresh();
        $this->assertEquals('pending_price_confirmation', $order->status);
        $this->assertStringContainsString('طلب العميل تجديد تسعيرة اليوم', $order->admin_price_notes);
    }

    public function test_expire_stale_quotes_command_marks_expired_and_releases_reservations()
    {
        $order = Order::create([
            'customer_name' => 'Nader Samy',
            'customer_phone' => '01077778888',
            'shipping_address' => 'Suez, Egypt',
            'items_count' => 1,
            'subtotal' => 300.00,
            'shipping_cost' => 50.00,
            'total' => 350.00,
            'status' => 'pending_price_confirmation',
            'payment_status' => 'unpaid',
            'payment_token' => 'token-stale-7007',
            'price_expires_at' => Carbon::now()->subHours(26), // past 24h grace window
            'session_id' => 'session-stale-7007',
        ]);

        $reservation = StockReservation::create([
            'order_id' => $order->id,
            'session_id' => 'session-stale-7007',
            'quantity' => 10,
            'expires_at' => Carbon::now()->subHours(2),
        ]);

        $this->artisan('print:expire-stale-quotes')
            ->assertSuccessful();

        $order->refresh();
        $this->assertEquals('expired', $order->status);

        // Reservation released
        $this->assertDatabaseMissing('stock_reservations', ['id' => $reservation->id]);
    }
}
