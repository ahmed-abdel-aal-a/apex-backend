<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\ShippingCompany;
use App\Models\ShippingZone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

class BostaShippingProvider implements ShippingProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null)
    {
        if ($apiKey) {
            $this->apiKey = $apiKey;
        } else {
            $company = ShippingCompany::where('code', 'bosta')->where('is_active', true)->first();
            $this->apiKey = (string)($company?->credentials['api_key'] ?? config('services.bosta.api_key', ''));
        }

        $this->baseUrl = rtrim($baseUrl ?: (string)config('services.bosta.base_url', 'https://app.bosta.co/api/v2'), '/');
    }

    /**
     * احتساب تكلفة الشحن التقديرية أو التراجع لحساب المناطق الثابتة
     */
    public function calculateCost(Order $order): ?float
    {
        if ($order->zone_id) {
            $zone = ShippingZone::find($order->zone_id);
            if ($zone) {
                return (float)$zone->rate;
            }
        }

        return (float)($order->shipping_cost ?? 0.0);
    }

    /**
     * حارس الأمان المالي واعتماد القائمة البيضاء (Whitelist) لاحتساب الـ COD
     * يمنع منعاً باتاً شحن أي طلب إلكتروني غير مسدد أو معلق مجاناً
     *
     * @throws DomainException
     */
    public function calculateCodAmount(Order $order): float
    {
        // 1. الطلب مسدد بالكامل إلكترونياً
        if ($order->payment_status === 'paid') {
            return 0.00;
        }

        // 2. طلب مطبعة مسدد عربون جزئي (Partially Paid Deposit)
        if ($order->payment_status === 'partially_paid') {
            $remaining = (float)$order->remaining_amount > 0
                ? (float)$order->remaining_amount
                : ((float)$order->total - (float)$order->deposit_amount);

            if ($remaining <= 0) {
                throw new DomainException("طلب العربون رقم {$order->order_number} مسجل كمدفوع جزئياً ولكن المبلغ المتبقي المحسوب هو صفر أو سالب.");
            }

            return round($remaining, 2);
        }

        // 3. دفع كامل عند الاستلام (COD Full Payment)
        // شرط حازم: يجب أن تكون طريقة الدفع COD، ونوع السداد ليس عربوناً (يمنع شحن طلبات العربون قبل تحصيل عربونها)
        if ($order->payment_status === 'unpaid' && $order->payment_method === 'cod' && $order->payment_type !== 'deposit') {
            $totalAmount = (float)($order->remaining_amount > 0 ? $order->remaining_amount : $order->total);
            if ($totalAmount <= 0) {
                throw new DomainException("طلب الدفع عند الاستلام رقم {$order->order_number} يحتوي على إجمالي غير صالح للتحصيل ({$totalAmount}).");
            }
            return round($totalAmount, 2);
        }

        // 4. حظر وتجريم أي حالة دفع أخرى (awaiting_verification, pending, failed, refunded, أو أونلاين unpaid)
        throw new DomainException(
            "حظر أمني: لا يمكن إصدار بوليصة شحن للطلب رقم {$order->order_number} في وضعه المالي الحالي " .
            "(حالة الدفع: '{$order->payment_status}' | طريقة الدفع: '{$order->payment_method}' | نوع السداد: '{$order->payment_type}'). " .
            "يرجى مراجعة إيصال السداد أو تأكيد الدفع قبل تجهيز الشحنة."
        );
    }

    /**
     * تطبيع وتطهير بيانات المستلم لتفادي أخطاء الـ Validation الصارمة في Bosta API v2
     *
     * @throws InvalidArgumentException
     */
    public function sanitizeReceiverData(Order $order): array
    {
        // أ) الاسم: تقسيم لاسم أول واسم أخير ومعالجة الاسم الفردي
        $rawName = trim((string)($order->customer_name ?: 'عميل المتجر'));
        $nameParts = preg_split('/\s+/', $rawName, 2);
        $firstName = !empty($nameParts[0]) ? $nameParts[0] : 'عميل';
        $lastName = !empty($nameParts[1]) ? $nameParts[1] : '.';

        // ب) الهاتف: تطبيع الصيغة المصرية القياسية ^01[0125][0-9]{8}$
        $rawPhone = preg_replace('/[^\d]/', '', (string)$order->customer_phone);
        // إزالة بادئة الدولة 0020 أو +20 أو 20 إن وجدت
        if (str_starts_with($rawPhone, '0020')) {
            $rawPhone = substr($rawPhone, 4);
        } elseif (str_starts_with($rawPhone, '20') && strlen($rawPhone) === 12) {
            $rawPhone = substr($rawPhone, 2);
        }
        // إضافة الصفر إذا بدأ بـ 1
        if (str_starts_with($rawPhone, '1') && strlen($rawPhone) === 10) {
            $rawPhone = '0' . $rawPhone;
        }

        if (!preg_match('/^01[0125][0-9]{8}$/', $rawPhone)) {
            throw new InvalidArgumentException(
                "رقم هاتف العميل '{$order->customer_phone}' غير صالح لشركة بوسطة. يجب أن يكون رقماً مصرياً صحيحاً مكوناً من 11 رقماً يبدأ بـ (010, 011, 012, 015)."
            );
        }

        // ج) المحافظة والعنوان
        $order->loadMissing('zone');
        $governorate = $order->zone?->name ?: 'القاهرة';
        $bostaCity = $this->mapGovernorateToBostaCity($governorate);

        $addressLine = trim((string)($order->shipping_address ?: 'العنوان مسجل بالطلب'));
        // إذا كان طول العنوان أقل من 5 أحرف، ندمجه مع اسم المحافظة والمدينة لتفادي رفض بوسطة
        if (mb_strlen($addressLine) < 5) {
            $addressLine = "{$addressLine} - {$governorate}";
        }

        return [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'phone' => $rawPhone,
            'city' => $bostaCity,
            'firstLine' => $addressLine,
        ];
    }

    /**
     * مطابقة المحافظة المصرية مع أسماء ومراكز توزيع بوسطة الرسمية
     */
    public function mapGovernorateToBostaCity(string $govName): string
    {
        $govMap = [
            'القاهرة' => 'Cairo',
            'الجيزة' => 'Giza',
            'الإسكندرية' => 'Alexandria',
            'القليوبية' => 'Qalyubia',
            'الشرقية' => 'Sharqia',
            'الدقهلية' => 'Dakahlia',
            'المنوفية' => 'Monufia',
            'الغربية' => 'Gharbia',
            'البحيرة' => 'Beheira',
            'دمياط' => 'Damietta',
            'كفر الشيخ' => 'Kafr El Sheikh',
            'بورسعيد' => 'Port Said',
            'الإسماعيلية' => 'Ismailia',
            'السويس' => 'Suez',
            'الفيوم' => 'Fayoum',
            'بني سويف' => 'Beni Suef',
            'المنيا' => 'Minya',
            'أسيوط' => 'Asyut',
            'سوهاج' => 'Sohag',
            'قنا' => 'Qena',
            'الأقصر' => 'Luxor',
            'أسوان' => 'Aswan',
            'البحر الأحمر' => 'Red Sea',
            'الوادي الجديد' => 'New Valley',
            'مطروح' => 'Matrouh',
            'شمال سيناء' => 'North Sinai',
            'جنوب سيناء' => 'South Sinai',
        ];

        return $govMap[$govName] ?? $govName;
    }

    /**
     * إنشاء بوليصة الشحن لدى بوسطة (POST /api/v2/deliveries)
     *
     * @throws RuntimeException|DomainException|InvalidArgumentException
     */
    public function createWaybill(Order $order): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException("مفتاح Bosta API غير مسجل في إعدادات شركات الشحن. يرجى إضافته أولاً في لوحة التحكم.");
        }

        // فحص حارس الدفع بالقائمة البيضاء
        $codAmount = $this->calculateCodAmount($order);

        // تطبيع بيانات المستلم
        $receiver = $this->sanitizeReceiverData($order);

        // احتساب نوع الطرد
        $itemsCount = (int)($order->items_count > 0 ? $order->items_count : 1);
        $packageType = 'Small';
        if ($itemsCount >= 6) {
            $packageType = 'Large';
        } elseif ($itemsCount >= 3) {
            $packageType = 'Medium';
        }

        // صياغة محتويات الطرد وملاحظات المطبعة الصارمة
        $description = "طلب مطبعة {$order->order_number} ({$itemsCount} عناصر)";
        $notes = trim((string)($order->notes ?? ''));
        $printShopNotice = "مطبوعات ورقية هامة - يرجى الحفاظ على الطرد من الطي أو البلل.";
        $fullNotes = !empty($notes) ? "{$notes} | {$printShopNotice}" : $printShopNotice;

        $payload = [
            'type' => 10, // Deliver
            'specs' => [
                'packageType' => $packageType,
                'packageDetails' => [
                    'itemsCount' => $itemsCount,
                    'description' => $description,
                ],
            ],
            'receiver' => [
                'firstName' => $receiver['firstName'],
                'lastName' => $receiver['lastName'],
                'phone' => $receiver['phone'],
            ],
            'dropOffAddress' => [
                'firstLine' => $receiver['firstLine'],
                'city' => $receiver['city'],
            ],
            'cod' => (float)$codAmount,
            'businessReference' => (string)($order->order_number ?: "#ORD-{$order->id}"),
            'notes' => $fullNotes,
            'allowToOpenPackage' => false, // للمطبوعات المخصصة الافتراضي غير مسموح لحماية المطبوعات من التلف
        ];

        Log::info("[BostaShippingProvider] Sending create delivery request for Order {$order->id}", [
            'businessReference' => $payload['businessReference'],
            'cod' => $payload['cod'],
            'city' => $payload['dropOffAddress']['city'],
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post("{$this->baseUrl}/deliveries", $payload);

            if (!$response->successful()) {
                $errBody = $response->json();
                $errMsg = $errBody['message'] ?? $errBody['error'] ?? $response->body();
                Log::error("[BostaShippingProvider] API Error for Order {$order->id}: HTTP {$response->status()} - {$errMsg}");
                throw new RuntimeException("فشل إصدار بوليصة بوسطة ({$response->status()}): {$errMsg}");
            }

            $resData = $response->json('data') ?? $response->json();
            $trackingNumber = $resData['trackingNumber'] ?? $resData['tracking_number'] ?? null;
            $providerId = $resData['_id'] ?? $resData['id'] ?? null;

            if (empty($trackingNumber)) {
                throw new RuntimeException("استجابت بوسطة بنجاح ولكن لم يتم إرجاع رقم التتبع (trackingNumber).");
            }

            Log::info("[BostaShippingProvider] Successfully created waybill for Order {$order->id}: Tracking {$trackingNumber}, ID {$providerId}");

            return [
                'tracking_number' => (string)$trackingNumber,
                'shipping_provider_id' => (string)$providerId,
                'raw_response' => $resData,
            ];
        } catch (\Exception $e) {
            if ($e instanceof RuntimeException || $e instanceof DomainException || $e instanceof InvalidArgumentException) {
                throw $e;
            }
            Log::error("[BostaShippingProvider] Network/Exception for Order {$order->id}: " . $e->getMessage());
            throw new RuntimeException("تعذر الاتصال بخوادم بوسطة: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * استخراج وتنزيل البوليصة الرسمية AWB بصيغة PDF
     * مع التحقق الصارم من ترويسة %PDF- لمنع تسليم ملفات تالفة للمشرف
     *
     * @throws RuntimeException
     */
    public function getAwbPdf(Order $order, string $format = 'A6', string $lang = 'ar'): string
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException("مفتاح Bosta API غير مسجل.");
        }

        $trackingNumber = $order->tracking_number;
        $providerId = $order->shipping_provider_id;

        if (empty($trackingNumber) && empty($providerId)) {
            throw new RuntimeException("الطلب رقم {$order->order_number} ليس له رقم تتبع أو معرّف بوليصة لدى بوسطة.");
        }

        $payload = [
            'requestedAwbType' => strtoupper($format) === 'A4' ? 'A4' : 'A6',
            'lang' => in_array(strtolower($lang), ['ar', 'en']) ? strtolower($lang) : 'ar',
        ];

        if (!empty($trackingNumber)) {
            $payload['trackingNumbers'] = (string)$trackingNumber;
        } else {
            $payload['ids'] = [(string)$providerId];
        }

        Log::info("[BostaShippingProvider] Fetching mass-awb PDF for Order {$order->id}", $payload);

        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(20)->post("{$this->baseUrl}/deliveries/mass-awb", $payload);

        if (!$response->successful()) {
            $err = $response->json('message') ?? $response->body();
            throw new RuntimeException("فشل جلب بوليصة الشحن من بوسطة ({$response->status()}): {$err}");
        }

        $json = $response->json();
        $rawPdfData = null;

        if (isset($json['data'])) {
            // Base64 encoded string
            $rawPdfData = base64_decode((string)$json['data']);
        } else {
            // Direct binary response
            $rawPdfData = $response->body();
        }

        // فحص ترويسة %PDF- لضمان سلامة الملف
        if (!$rawPdfData || !str_starts_with($rawPdfData, '%PDF-')) {
            throw new RuntimeException("الملف المستلم من خوادم بوسطة ليس ملف PDF صالحاً أو أن البوليصة قيد المعالجة ولم تجهز بعد للطباعة.");
        }

        return $rawPdfData;
    }

    /**
     * تتبع حالة الشحنة المباشرة
     */
    public function trackShipment(string $trackingNumber): string
    {
        if (empty($this->apiKey)) {
            return 'ready_for_pickup';
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->get("{$this->baseUrl}/deliveries/{$trackingNumber}");

            if ($response->successful()) {
                $state = $response->json('data.state') ?? $response->json('data.status') ?? $response->json('state');
                return self::mapBostaState($state);
            }
        } catch (\Throwable $e) {
            Log::warning("[BostaShippingProvider] trackShipment error: " . $e->getMessage());
        }

        return 'ready_for_pickup';
    }

    /**
     * محرك مطابقة وترجمة حالات بوسطة الـ 37 (Bosta States) الرقمية والنصية والكائنات
     */
    public static function mapBostaState(mixed $state): string
    {
        $code = null;
        $value = null;

        if (is_array($state)) {
            $code = $state['code'] ?? null;
            $value = $state['value'] ?? ($state['name'] ?? null);
        } elseif (is_numeric($state)) {
            $code = (int)$state;
        } elseif (is_string($state)) {
            $value = $state;
        }

        $normValue = strtolower(trim((string)$value));
        $normCode = is_numeric($code) ? (int)$code : null;

        // 1. حالات الفشل والاستثناء والرفض والتلف (أولوية أولى لمنع تداخل الكلمات)
        if (in_array($normCode, [47, 100, 101], true)
            || str_contains($normValue, 'exception')
            || str_contains($normValue, 'lost')
            || str_contains($normValue, 'damage')
            || str_contains($normValue, 'fail')
            || str_contains($normValue, 'refus')
            || str_contains($normValue, 'postpon')
            || str_contains($normValue, 'undeliver')
            || (str_contains($normValue, 'return') && !str_contains($normValue, 'returned to business') && !str_contains($normValue, 'returned to stock'))) {
            return 'returned'; // أو delivery_failed
        }

        // 2. مرتجع وصل المخزن رسمياً (Returned to Store / Business)
        if (in_array($normCode, [46, 60], true) || str_contains($normValue, 'returned to business') || str_contains($normValue, 'returned to stock')) {
            return 'returned';
        }

        // 3. ملغى أو تم إنهاؤه (Cancelled / Terminated)
        if (in_array($normCode, [48, 49], true) || str_contains($normValue, 'cancel') || str_contains($normValue, 'terminat')) {
            return 'cancelled';
        }

        // 4. في الطريق للتسليم مع المندوب (In Transit / Out for Delivery / Delivering)
        if (in_array($normCode, [20, 21, 23, 24, 25, 30, 40], true)
            || str_contains($normValue, 'delivering')
            || str_contains($normValue, 'out for delivery')
            || str_contains($normValue, 'in transit')
            || str_contains($normValue, 'picked up')
            || str_contains($normValue, 'arrived at customer')
            || str_contains($normValue, 'route assigned')) {
            return 'shipped';
        }

        // 5. تم التسليم للعميل بنجاح (Delivered)
        if ($normCode === 45 || (str_contains($normValue, 'deliver') && !str_contains($normValue, 'delivering') && !str_contains($normValue, 'undeliver'))) {
            return 'completed';
        }

        // 6. بانتظار المندوب / أنشئت حديثاً (Ready for Pickup / New / Draft)
        if (in_array($normCode, [10, 11], true)
            || str_contains($normValue, 'pickup requested')
            || str_contains($normValue, 'draft')
            || str_contains($normValue, 'new')
            || str_contains($normValue, 'created')) {
            return 'ready_for_pickup';
        }

        return 'ready_for_pickup';
    }

    /**
     * تفكيك وتوحيد حمولة الويب هوك من بوسطة
     */
    public function parseWebhook(array $payload): array
    {
        $trackingNumber = $payload['trackingNumber']
            ?? $payload['tracking_number']
            ?? $payload['data']['trackingNumber']
            ?? $payload['data']['tracking_number']
            ?? null;

        $providerId = $payload['_id']
            ?? $payload['id']
            ?? $payload['data']['_id']
            ?? $payload['data']['id']
            ?? null;

        $rawState = $payload['state']
            ?? $payload['status']
            ?? $payload['data']['state']
            ?? $payload['data']['status']
            ?? null;

        $mappedStatus = self::mapBostaState($rawState);

        $timestamp = isset($payload['timestamp'])
            ? Carbon::parse($payload['timestamp'])
            : now();

        return [
            'tracking_number' => $trackingNumber ? (string)$trackingNumber : null,
            'shipping_provider_id' => $providerId ? (string)$providerId : null,
            'status' => $mappedStatus,
            'raw_status' => is_scalar($rawState) ? (string)$rawState : json_encode($rawState),
            'timestamp' => $timestamp,
        ];
    }
}
