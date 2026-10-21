<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Setting;
use App\Models\ShippingCompany;
use App\Models\PaymentGateway;
use App\Models\ShippingZone;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Settings
        Setting::firstOrCreate(['key' => 'cod_enabled'], ['value' => true]);
        Setting::firstOrCreate(['key' => 'cod_fee'], ['value' => 15]);
        Setting::firstOrCreate(['key' => 'free_shipping_enabled'], ['value' => true]);
        Setting::firstOrCreate(['key' => 'free_shipping_threshold'], ['value' => 1000]);
        Setting::firstOrCreate(['key' => 'warehouse_governorate'], ['value' => 'القاهرة']);
        Setting::firstOrCreate(['key' => 'warehouse_city'], ['value' => 'مدينة نصر']);
        Setting::firstOrCreate(['key' => 'warehouse_address'], ['value' => 'شارع عباس العقاد، متفرع من مصطفى النحاس، مبنى رقم 15، الدور الثاني']);

        // Shipping Companies
        $companies = [
            ['code' => 'bosta', 'name' => 'بوسطة (Bosta)', 'description' => 'توصيل سريع وموثوق في جميع أنحاء مصر مع دعم الدفع عند الاستلام.', 'is_active' => true, 'is_configured' => true, 'cod_supported' => true, 'credentials' => ['apiKey' => 'test_api_key_bosta']],
            ['code' => 'aramex', 'name' => 'أرامكس (Aramex)', 'description' => 'تغطية شاملة داخل مصر وخدمات شحن دولية.', 'is_active' => false, 'is_configured' => true, 'cod_supported' => true, 'credentials' => ['username' => 'test', 'password' => 'test', 'accountNumber' => 'test', 'accountPin' => 'test']],
            ['code' => 'mylerz', 'name' => 'مايلرز (Mylerz)', 'description' => 'توصيل في نفس اليوم لبعض المناطق وخدمة عملاء ممتازة.', 'is_active' => true, 'is_configured' => false, 'cod_supported' => true, 'credentials' => null],
            ['code' => 'sprint', 'name' => 'سبرينت (Sprint)', 'description' => 'حلول لوجستية متكاملة للتجارة الإلكترونية.', 'is_active' => false, 'is_configured' => false, 'cod_supported' => true, 'credentials' => null],
            ['code' => 'r2s', 'name' => 'R2S Logistics', 'description' => 'شحن وتوصيل وإدارة مرتجعات بكفاءة عالية.', 'is_active' => false, 'is_configured' => false, 'cod_supported' => true, 'credentials' => null],
        ];

        foreach ($companies as $company) {
            ShippingCompany::updateOrCreate(['code' => $company['code']], $company);
        }

        // Payment Gateways
        $gateways = [
            ['code' => 'kashier', 'name' => 'كاشير (Kashier)', 'type' => ['cards', 'wallets'], 'is_active' => true, 'is_configured' => false, 'credentials' => null],
            ['code' => 'paymob', 'name' => 'باي موب (Paymob)', 'type' => ['cards', 'wallets', 'kiosk'], 'is_active' => false, 'is_configured' => false, 'credentials' => null],
            ['code' => 'fawry', 'name' => 'فوري (Fawry)', 'type' => ['cash', 'cards'], 'is_active' => false, 'is_configured' => false, 'credentials' => null],
        ];

        foreach ($gateways as $gateway) {
            PaymentGateway::updateOrCreate(['code' => $gateway['code']], $gateway);
        }

        // Shipping Zones
        $zones = [
            ['name' => 'القاهرة والجيزة', 'rate' => 45, 'delivery_days' => '1-2'],
            ['name' => 'الإسكندرية', 'rate' => 55, 'delivery_days' => '2-3'],
            ['name' => 'محافظات الدلتا', 'rate' => 65, 'delivery_days' => '2-4'],
            ['name' => 'مدن القناة', 'rate' => 65, 'delivery_days' => '2-4'],
            ['name' => 'الصعيد', 'rate' => 80, 'delivery_days' => '3-5'],
            ['name' => 'البحر الأحمر وسيناء', 'rate' => 95, 'delivery_days' => '4-7'],
        ];

        foreach ($zones as $zone) {
            ShippingZone::firstOrCreate(['name' => $zone['name']], $zone);
        }
    }
}
