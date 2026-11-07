<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update Chat Settings table
        if (Schema::hasTable('chat_settings')) {
            DB::table('chat_settings')->update([
                'whatsapp_number' => '01011348890',
                'whatsapp_enabled' => true,
                'whatsapp_default_message' => 'مرحباً مطبعة Print Express، أود الاستفسار عن تفاصيل وخدمات الطباعة.',
            ]);
        }

        // 2. Update Settings table with JSON-encoded values
        if (Schema::hasTable('settings')) {
            $settings = [
                'whatsapp_number' => '01011348890',
                'store_phone' => '01011348890',
                'owner_phone' => '01023336371',
                'store_location_map' => 'https://maps.app.goo.gl/BE6b7dTrhY3hH1BR8?g_st=ic',
                'google_maps_url' => 'https://maps.app.goo.gl/BE6b7dTrhY3hH1BR8?g_st=ic',
                'social_facebook' => 'https://www.facebook.com/share/1CS2Nj9hVW/?mibextid=wwXIfr',
                'social_instagram' => 'https://www.instagram.com/printexpresseg.press?dlrf=MThqMGF5bnJkbmdobg%3D%3D&utm_source=qr',
                'social_tiktok' => 'https://www.tiktok.com/@print.express12?_r=1&_t=ZS-9B4Bt50ZxIu',
                'social_youtube' => 'https://youtube.com/@printexpress1501?si=vr-hHeCnfPWhSCZn',
                'store_name' => 'Print Express',
            ];

            foreach ($settings as $key => $value) {
                \App\Models\Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }
        }

        Cache::forget('store_settings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
