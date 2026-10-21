<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\ShippingZone;

return new class extends Migration
{
    public function up(): void
    {
        $allGovernorates = [
            1  => ['name' => 'القاهرة', 'delivery_days' => '1 - 2', 'rate' => 45.00],
            2  => ['name' => 'الجيزة', 'delivery_days' => '1 - 2', 'rate' => 45.00],
            3  => ['name' => 'الإسكندرية', 'delivery_days' => '2 - 3', 'rate' => 55.00],
            4  => ['name' => 'القليوبية', 'delivery_days' => '2 - 3', 'rate' => 55.00],
            5  => ['name' => 'الشرقية', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            6  => ['name' => 'الدقهلية', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            7  => ['name' => 'المنوفية', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            8  => ['name' => 'الغربية', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            9  => ['name' => 'البحيرة', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            10 => ['name' => 'دمياط', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            11 => ['name' => 'كفر الشيخ', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            12 => ['name' => 'بورسعيد', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            13 => ['name' => 'الإسماعيلية', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            14 => ['name' => 'السويس', 'delivery_days' => '2 - 3', 'rate' => 60.00],
            15 => ['name' => 'الفيوم', 'delivery_days' => '2 - 3', 'rate' => 70.00],
            16 => ['name' => 'بني سويف', 'delivery_days' => '2 - 3', 'rate' => 75.00],
            17 => ['name' => 'المنيا', 'delivery_days' => '3 - 4', 'rate' => 75.00],
            18 => ['name' => 'أسيوط', 'delivery_days' => '3 - 4', 'rate' => 80.00],
            19 => ['name' => 'سوهاج', 'delivery_days' => '3 - 4', 'rate' => 85.00],
            20 => ['name' => 'قنا', 'delivery_days' => '3 - 5', 'rate' => 85.00],
            21 => ['name' => 'الأقصر', 'delivery_days' => '3 - 5', 'rate' => 90.00],
            22 => ['name' => 'أسوان', 'delivery_days' => '3 - 5', 'rate' => 90.00],
            23 => ['name' => 'البحر الأحمر', 'delivery_days' => '3 - 5', 'rate' => 100.00],
            24 => ['name' => 'الوادي الجديد', 'delivery_days' => '4 - 6', 'rate' => 110.00],
            25 => ['name' => 'مطروح', 'delivery_days' => '3 - 5', 'rate' => 100.00],
            26 => ['name' => 'شمال سيناء', 'delivery_days' => '4 - 6', 'rate' => 110.00],
            27 => ['name' => 'جنوب سيناء', 'delivery_days' => '4 - 6', 'rate' => 110.00],
        ];

        foreach ($allGovernorates as $id => $gov) {
            $existing = ShippingZone::find($id);
            if ($existing) {
                $existing->update([
                    'name' => $gov['name'],
                    'delivery_days' => $gov['delivery_days'],
                    'rate' => $gov['rate'],
                ]);
            } else {
                ShippingZone::updateOrCreate(
                    ['name' => $gov['name']],
                    [
                        'id' => $id,
                        'delivery_days' => $gov['delivery_days'],
                        'rate' => $gov['rate'],
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        // No-op to preserve integrity
    }
};
