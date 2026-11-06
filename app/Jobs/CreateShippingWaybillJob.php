<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Shipping\ShippingFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use DomainException;
use InvalidArgumentException;
use Throwable;

class CreateShippingWaybillJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    public int|string $orderId;

    public function __construct(int|string $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * الحماية الفريدة لمنع تشغيل مهمتين لنفس الطلب في نفس اللحظة (Idempotent Job Lock)
     */
    public function uniqueId(): string
    {
        return (string)$this->orderId;
    }

    public function handle(ShippingFactory $shippingFactory): void
    {
        DB::transaction(function () use ($shippingFactory) {
            $order = Order::where('id', $this->orderId)->lockForUpdate()->first();

            if (!$order) {
                Log::error("CreateShippingWaybillJob: Order {$this->orderId} not found in database.");
                return;
            }

            // 1. حارس الحالات: إلغاء الإصدار إذا كان الطلب ملغياً، مسلماً، أو في انتظار تأكيد السعر
            if (in_array($order->status, ['pending_price_confirmation', 'cancelled', 'refunded', 'completed', 'delivered'])) {
                Log::warning("CreateShippingWaybillJob: Order {$this->orderId} is in status '{$order->status}'. Aborting waybill creation.");
                return;
            }

            // 2. حارس عدم التكرار (Strict Idempotency): إذا كان هناك رقم تتبع حقيقي موجود مسبقاً، نتوقف فوراً
            if (!empty($order->tracking_number) && !str_starts_with($order->tracking_number, 'AWB-')) {
                Log::info("CreateShippingWaybillJob: Order {$this->orderId} already has a valid tracking number '{$order->tracking_number}'. Skipping.");
                return;
            }

            try {
                // الحصول على المزود واستدعاء إنشاء البوليصة
                $provider = $shippingFactory->getProvider($order);
                $result = $provider->createWaybill($order);

                // تحديث بيانات الطلب بعد النجاح الفعلي من بوسطة
                $order->tracking_number = $result['tracking_number'];
                $order->shipping_provider_id = $result['shipping_provider_id'] ?? null;
                $order->shipping_company = 'bosta';
                $order->shipping_status = 'ready_for_pickup';
                $order->latest_shipping_update_at = now();
                $order->shipping_error_log = null; // مسح أي أخطاء سابقة

                // نقل الحالة إلى جاهز لاستلام المندوب
                if (in_array($order->status, ['ready_for_shipping', 'processing', 'confirmed'])) {
                    $order->status = 'ready_for_pickup';
                }

                $order->save();

                Log::info("CreateShippingWaybillJob: Successfully generated Bosta waybill '{$order->tracking_number}' for Order #{$order->id}");
            } catch (Throwable $e) {
                Log::error("CreateShippingWaybillJob: Failed for Order #{$order->id}. Reason: " . $e->getMessage());

                // تسجيل سبب الخطأ في الداتابيز ليظهر للمشرف في الداشبورد
                $order->shipping_error_log = $e->getMessage();
                // إبقاء الحالة في ready_for_shipping وتجنب نقله لـ ready_for_pickup نهائياً
                if ($order->status === 'ready_for_pickup') {
                    $order->status = 'ready_for_shipping';
                }
                $order->save();

                // في حال كان الخطأ منطقياً أو خطأ بيانات مستلم (هاتف خاطئ أو حالة دفع غير مسموحة)
                // يتم إيقاف إعادة المحاولة في الطابور فوراً لكي يقوم المشرف بتعديل بيانات العميل
                if ($e instanceof DomainException || $e instanceof InvalidArgumentException) {
                    $this->fail($e);
                    return;
                }

                // للأخطاء العارضة (انقطاع شبكة أو سيرفر بوسطة)، نرمي الاستثناء ليعيد الـ Queue المحاولة
                throw $e;
            }
        });
    }
}
