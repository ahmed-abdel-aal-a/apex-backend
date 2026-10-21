<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ShippingCompany;
use App\Models\ShippingZone;
use App\Models\Setting;

class ShippingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. قصر شركات الشحن على شركتين فقط (Slot 1 & Slot 2)
        // استخدام firstOrCreate يحمي أي مفاتيح API أو إعدادات مسجلة مسبقاً من المسح
        $defaultCompanies = [
            [
                'code' => 'bosta',
                'name' => 'بوسطة (Bosta)',
                'description' => 'توصيل محلي سريع لجميع المحافظات والدفع عند الاستلام',
                'is_active' => true,
                'is_configured' => false,
                'cod_supported' => true,
                'credentials' => ['api_key' => ''],
            ],
            [
                'code' => 'aramex',
                'name' => 'أرامكس (Aramex)',
                'description' => 'شحن وتوزيع محلي ودولي لكافة المناطق والمحافظات',
                'is_active' => false,
                'is_configured' => false,
                'cod_supported' => true,
                'credentials' => [
                    'username' => '',
                    'password' => '',
                    'account_number' => '',
                    'account_pin' => '',
                ],
            ],
        ];

        foreach ($defaultCompanies as $companyData) {
            ShippingCompany::firstOrCreate(
                ['code' => $companyData['code']],
                $companyData
            );
        }

        // 2. تهيئة محافظات مصر الـ 27 (بدون الكتابة فوق أسعار معدلة إذا وجدت)
        $zones = [
            ['name' => 'القاهرة', 'delivery_days' => '1 - 2', 'rate' => 45.00],
            ['name' => 'الجيزة', 'delivery_days' => '1 - 2', 'rate' => 45.00],
            ['name' => 'الإسكندرية', 'delivery_days' => '2 - 3', 'rate' => 55.00],
            ['name' => 'القليوبية', 'delivery_days' => '2 - 3', 'rate' => 55.00],
            ['name' => 'الشرقية', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            ['name' => 'الدقهلية', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            ['name' => 'المنوفية', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            ['name' => 'الغربية', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            ['name' => 'البحيرة', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            ['name' => 'دمياط', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            ['name' => 'كفر الشيخ', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            ['name' => 'بورسعيد', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            ['name' => 'الإسماعيلية', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            ['name' => 'السويس', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            ['name' => 'الفيوم', 'delivery_days' => '2 - 3', 'rate' => 70.00],
            ['name' => 'بني سويف', 'delivery_days' => '2 - 3', 'rate' => 75.00],
            ['name' => 'المنيا', 'delivery_days' => '3 - 4', 'rate' => 75.00],
            ['name' => 'أسيوط', 'delivery_days' => '3 - 4', 'rate' => 80.00],
            ['name' => 'سوهاج', 'delivery_days' => '3 - 4', 'rate' => 85.00],
            ['name' => 'قنا', 'delivery_days' => '3 - 5', 'rate' => 85.00],
            ['name' => 'الأقصر', 'delivery_days' => '3 - 5', 'rate' => 90.00],
            ['name' => 'أسوان', 'delivery_days' => '3 - 5', 'rate' => 90.00],
            ['name' => 'البحر الأحمر', 'delivery_days' => '3 - 5', 'rate' => 100.00],
            ['name' => 'الوادي الجديد', 'delivery_days' => '4 - 6', 'rate' => 110.00],
            ['name' => 'مطروح', 'delivery_days' => '3 - 5', 'rate' => 100.00],
            ['name' => 'شمال سيناء', 'delivery_days' => '4 - 6', 'rate' => 110.00],
            ['name' => 'جنوب سيناء', 'delivery_days' => '4 - 6', 'rate' => 110.00],
        ];

        foreach ($zones as $zone) {
            ShippingZone::firstOrCreate(
                ['name' => $zone['name']],
                ['delivery_days' => $zone['delivery_days'], 'rate' => $zone['rate']]
            );
        }

        // 3. تهيئة الإعدادات الافتراضية للشحن العادي والسريع
        $defaultSettings = [
            'cod_enabled' => true,
            'cod_fee' => 15.00,
            'free_shipping_enabled' => false,
            'free_shipping_threshold' => 1000.00,
            'express_shipping_enabled' => true,
            'express_shipping_fee' => 35.00,
            'express_shipping_delivery_time' => 'خلال 24 - 48 ساعة كحد أقصى',
            'standard_shipping_company' => 'bosta',
            'express_shipping_company' => 'bosta',
            'warehouse_governorate' => 'القاهرة',
            'warehouse_city' => 'مدينة نصر',
            'warehouse_address' => 'شارع عباس العقاد - المقر الإداري',
        ];

        foreach ($defaultSettings as $key => $val) {
            Setting::firstOrCreate(['key' => $key], ['value' => $val]);
        }
    }
}
