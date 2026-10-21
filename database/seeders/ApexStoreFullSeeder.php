<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Attribute;
use App\Models\AddonGroup;
use App\Models\ProductAddon;
use App\Models\Product;
use App\Models\ProductVariant;

class ApexStoreFullSeeder extends Seeder
{
    private $catPaper;
    private $catGifts;
    private $catCards;
    private $catClothes;

    private $wrapping;
    private $namePrinting;
    private $express;
    private $warranty;

    public function run(): void
    {
        DB::transaction(function () {
            $this->seedCategories();
            $this->seedAttributes();
            $this->seedAddonGroups();
            $this->seedProducts();
            $this->linkAddonsToCategories();
        });
        
        $this->command->info('✅ ApexStoreFullSeeder completed successfully!');
    }

    private function seedCategories(): void
    {
        $this->catPaper = Category::firstOrCreate(
            ['name' => 'مطبوعات ورقية'],
            ['is_active' => true]
        );
        $this->catGifts = Category::firstOrCreate(
            ['name' => 'هدايا دعائية'],
            ['is_active' => true]
        );
        $this->catCards = Category::firstOrCreate(
            ['name' => 'كروت وبطاقات'],
            ['parent_id' => $this->catPaper->id, 'is_active' => true]
        );
        $this->catClothes = Category::firstOrCreate(
            ['name' => 'ملابس مخصصة'],
            ['parent_id' => $this->catGifts->id, 'is_active' => true]
        );
        $this->command->info('  → تم إنشاء 4 تصنيفات');
    }

    private function seedAttributes(): void
    {
        Attribute::firstOrCreate(
            ['name' => 'المقاس'],
            ['values' => ['S', 'M', 'L', 'XL']]
        );
        Attribute::firstOrCreate(
            ['name' => 'اللون'],
            ['values' => ['أبيض', 'أسود', 'كحلي', 'رمادي', 'ذهبي', 'فضي', 'نحاسي']]
        );
        $this->command->info('  → تم إنشاء 2 خاصية (المقاس، اللون)');
    }

    private function seedAddonGroups(): void
    {
        $this->wrapping = AddonGroup::firstOrCreate([
            'name->en' => 'Gift Wrapping',
        ], [
            'name' => ['en' => 'Gift Wrapping', 'ar' => 'تغليف هدايا'],
            'description' => ['en' => 'Gift wrapping service', 'ar' => 'خدمة تغليف الهدايا'],
            'input_type' => 'radio',
            'price_multiplier_type' => 'per_item',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ProductAddon::firstOrCreate([
            'addon_group_id' => $this->wrapping->id,
            'name->en' => 'Standard Wrapping',
        ], [
            'name' => ['en' => 'Standard Wrapping', 'ar' => 'تغليف عادي'],
            'type' => 'simple',
            'price' => 15,
            'price_type' => 'fixed',
            'is_active' => true,
        ]);

        ProductAddon::firstOrCreate([
            'addon_group_id' => $this->wrapping->id,
            'name->en' => 'Luxury Wrapping',
        ], [
            'name' => ['en' => 'Luxury Wrapping', 'ar' => 'تغليف فاخر'],
            'type' => 'simple',
            'price' => 35,
            'price_type' => 'fixed',
            'is_active' => true,
        ]);

        $this->namePrinting = AddonGroup::firstOrCreate([
            'name->en' => 'Name Printing',
        ], [
            'name' => ['en' => 'Name Printing', 'ar' => 'طباعة الاسم'],
            'description' => ['en' => 'Print your name on the product', 'ar' => 'طباعة اسمك على المنتج'],
            'input_type' => 'checkbox',
            'price_multiplier_type' => 'per_item',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        ProductAddon::firstOrCreate([
            'addon_group_id' => $this->namePrinting->id,
            'name->en' => 'Laser Printing',
        ], [
            'name' => ['en' => 'Laser Printing', 'ar' => 'طباعة بالليزر'],
            'type' => 'simple',
            'price' => 25,
            'price_type' => 'fixed',
            'is_active' => true,
        ]);

        ProductAddon::firstOrCreate([
            'addon_group_id' => $this->namePrinting->id,
            'name->en' => 'Engraving Printing',
        ], [
            'name' => ['en' => 'Engraving Printing', 'ar' => 'طباعة بالحفر'],
            'type' => 'simple',
            'price' => 40,
            'price_type' => 'fixed',
            'is_active' => true,
        ]);

        $this->express = AddonGroup::firstOrCreate([
            'name->en' => 'Express Delivery',
        ], [
            'name' => ['en' => 'Express Delivery', 'ar' => 'التوصيل السريع'],
            'description' => ['en' => 'Get it faster', 'ar' => 'احصل عليه أسرع'],
            'input_type' => 'radio',
            'price_multiplier_type' => 'per_order_line',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        ProductAddon::firstOrCreate([
            'addon_group_id' => $this->express->id,
            'name->en' => 'Same Day Delivery',
        ], [
            'name' => ['en' => 'Same Day Delivery', 'ar' => 'توصيل نفس اليوم'],
            'type' => 'simple',
            'price' => 80,
            'price_type' => 'fixed',
            'is_active' => true,
        ]);

        ProductAddon::firstOrCreate([
            'addon_group_id' => $this->express->id,
            'name->en' => '2 Hours Delivery',
        ], [
            'name' => ['en' => '2 Hours Delivery', 'ar' => 'توصيل خلال ساعتين'],
            'type' => 'simple',
            'price' => 120,
            'price_type' => 'fixed',
            'is_active' => true,
        ]);

        $this->warranty = AddonGroup::firstOrCreate([
            'name->en' => 'Extended Warranty',
        ], [
            'name' => ['en' => 'Extended Warranty', 'ar' => 'ضمان إضافي'],
            'description' => ['en' => 'Extend your product warranty', 'ar' => 'تمديد ضمان المنتج'],
            'input_type' => 'radio',
            'price_multiplier_type' => 'per_item',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 4,
        ]);

        ProductAddon::firstOrCreate([
            'addon_group_id' => $this->warranty->id,
            'name->en' => '6 Months Warranty',
        ], [
            'name' => ['en' => '6 Months Warranty', 'ar' => 'ضمان 6 شهور'],
            'type' => 'simple',
            'price' => 50,
            'price_type' => 'fixed',
            'is_active' => true,
        ]);

        ProductAddon::firstOrCreate([
            'addon_group_id' => $this->warranty->id,
            'name->en' => '1 Year Warranty',
        ], [
            'name' => ['en' => '1 Year Warranty', 'ar' => 'ضمان سنة'],
            'type' => 'simple',
            'price' => 90,
            'price_type' => 'fixed',
            'is_active' => true,
        ]);
        
        $this->command->info('  → تم إنشاء 4 مجموعات إضافية و 8 خدمات');
    }

    private function seedProducts(): void
    {
        // Simple Products
        $p1 = Product::firstOrCreate(['sku' => 'PE-BC-001'], [
            'category_id' => $this->catCards->id,
            'name' => 'كروت شخصية فاخرة',
            'slug' => 'luxury-business-cards',
            'description' => 'كروت شخصية فاخرة مطبوعة على ورق كوشيه 350 جرام مع لمسة لامعة UV.',
            'type' => 'simple',
            'price' => 150,
            'track_inventory' => true,
            'quantity' => 200,
            'is_active' => true,
        ]);
        $p1->addonGroups()->syncWithoutDetaching([$this->wrapping->id, $this->namePrinting->id]);

        $p2 = Product::firstOrCreate(['sku' => 'PE-BR-001'], [
            'category_id' => $this->catPaper->id,
            'name' => 'بروشور A4 مطوي',
            'slug' => 'folded-a4-brochure',
            'description' => 'بروشور A4 مطوي مثالي للترويج لخدماتك ومنتجاتك',
            'type' => 'simple',
            'price' => 200,
            'track_inventory' => true,
            'quantity' => 150,
            'is_active' => true,
        ]);
        $p2->addonGroups()->syncWithoutDetaching([$this->wrapping->id]);

        $p3 = Product::firstOrCreate(['sku' => 'PE-ST-001'], [
            'category_id' => $this->catPaper->id,
            'name' => 'ستيكرز لامعة (100 قطعة)',
            'slug' => 'glossy-stickers',
            'description' => 'مجموعة من 100 ستيكر لامع يمكن لصقه على أي سطح.',
            'type' => 'simple',
            'price' => 120,
            'track_inventory' => true,
            'quantity' => 500,
            'is_active' => true,
        ]);

        $p4 = Product::firstOrCreate(['sku' => 'PE-FL-001'], [
            'category_id' => $this->catPaper->id,
            'name' => 'فلاير إعلاني A5',
            'slug' => 'a5-promotional-flyer',
            'description' => 'فلاير إعلاني بحجم A5 يوصل رسالتك بوضوح.',
            'type' => 'simple',
            'price' => 80,
            'track_inventory' => true,
            'quantity' => 1000,
            'is_active' => true,
        ]);

        $p5 = Product::firstOrCreate(['sku' => 'PE-NB-001'], [
            'category_id' => $this->catGifts->id,
            'name' => 'دفتر ملاحظات مخصص',
            'slug' => 'custom-notebook',
            'description' => 'دفتر ملاحظات عالي الجودة بتصميم مخصص.',
            'type' => 'simple',
            'price' => 95,
            'track_inventory' => true,
            'quantity' => 300,
            'is_active' => true,
        ]);
        $p5->addonGroups()->syncWithoutDetaching([$this->wrapping->id, $this->warranty->id]);

        $p6 = Product::firstOrCreate(['sku' => 'PE-MG-001'], [
            'category_id' => $this->catGifts->id,
            'name' => 'أكواب سيراميك بالطباعة',
            'slug' => 'ceramic-printed-mugs',
            'description' => 'أكواب سيراميك مع طباعة تصميماتك المفضلة عالية الجودة.',
            'type' => 'simple',
            'price' => 180,
            'track_inventory' => true,
            'quantity' => 100,
            'is_active' => true,
        ]);
        $p6->addonGroups()->syncWithoutDetaching([$this->wrapping->id, $this->express->id]);

        // Variable Products
        
        // 1. T-Shirt
        $tshirt = Product::firstOrCreate(['sku' => 'PE-TS-001'], [
            'category_id' => $this->catClothes->id,
            'name' => 'تيشيرت قطن بالطباعة',
            'slug' => 'printed-cotton-tshirt',
            'description' => 'تيشيرت قطن 100% مع طباعة مميزة.',
            'type' => 'variable',
            'price' => 250,
            'track_inventory' => true,
            'quantity' => 0,
            'is_active' => true,
        ]);

        $tshirtVariants = [
            ['sku' => 'PE-TS-001-S-W', 'name' => 'S - أبيض', 'price' => 250, 'quantity' => 30, 'attrs' => [['name' => 'المقاس', 'value' => 'S'], ['name' => 'اللون', 'value' => 'أبيض']]],
            ['sku' => 'PE-TS-001-M-W', 'name' => 'M - أبيض', 'price' => 250, 'quantity' => 50, 'attrs' => [['name' => 'المقاس', 'value' => 'M'], ['name' => 'اللون', 'value' => 'أبيض']]],
            ['sku' => 'PE-TS-001-L-W', 'name' => 'L - أبيض', 'price' => 260, 'quantity' => 40, 'attrs' => [['name' => 'المقاس', 'value' => 'L'], ['name' => 'اللون', 'value' => 'أبيض']]],
            ['sku' => 'PE-TS-001-XL-W', 'name' => 'XL - أبيض', 'price' => 270, 'quantity' => 20, 'attrs' => [['name' => 'المقاس', 'value' => 'XL'], ['name' => 'اللون', 'value' => 'أبيض']]],
            ['sku' => 'PE-TS-001-S-B', 'name' => 'S - أسود', 'price' => 250, 'quantity' => 25, 'attrs' => [['name' => 'المقاس', 'value' => 'S'], ['name' => 'اللون', 'value' => 'أسود']]],
            ['sku' => 'PE-TS-001-M-B', 'name' => 'M - أسود', 'price' => 250, 'quantity' => 45, 'attrs' => [['name' => 'المقاس', 'value' => 'M'], ['name' => 'اللون', 'value' => 'أسود']]],
            ['sku' => 'PE-TS-001-L-B', 'name' => 'L - أسود', 'price' => 260, 'quantity' => 35, 'attrs' => [['name' => 'المقاس', 'value' => 'L'], ['name' => 'اللون', 'value' => 'أسود']]],
            ['sku' => 'PE-TS-001-XL-B', 'name' => 'XL - أسود', 'price' => 270, 'quantity' => 15, 'attrs' => [['name' => 'المقاس', 'value' => 'XL'], ['name' => 'اللون', 'value' => 'أسود']]],
        ];

        foreach ($tshirtVariants as $v) {
            ProductVariant::firstOrCreate([
                'product_id' => $tshirt->id,
                'sku' => $v['sku'],
            ], [
                'name' => $v['name'],
                'price' => $v['price'],
                'discount_price' => null,
                'track_inventory' => true,
                'quantity' => $v['quantity'],
                'attributes' => $v['attrs'],
            ]);
        }
        $tshirt->update([
            'quantity' => ProductVariant::where('product_id', $tshirt->id)->sum('quantity'),
            'price' => ProductVariant::where('product_id', $tshirt->id)->min('price'),
        ]);

        // 2. Polo Shirt
        $polo = Product::firstOrCreate(['sku' => 'PE-PL-001'], [
            'category_id' => $this->catClothes->id,
            'name' => 'بولو شيرت مطرز',
            'slug' => 'embroidered-polo-shirt',
            'description' => 'بولو شيرت مع تطريز شعارك الخاص.',
            'type' => 'variable',
            'price' => 320,
            'track_inventory' => true,
            'quantity' => 0,
            'is_active' => true,
        ]);

        $poloVariants = [
            ['sku' => 'PE-PL-001-M-N', 'name' => 'M - كحلي', 'price' => 320, 'quantity' => 20, 'attrs' => [['name' => 'المقاس', 'value' => 'M'], ['name' => 'اللون', 'value' => 'كحلي']]],
            ['sku' => 'PE-PL-001-L-N', 'name' => 'L - كحلي', 'price' => 320, 'quantity' => 30, 'attrs' => [['name' => 'المقاس', 'value' => 'L'], ['name' => 'اللون', 'value' => 'كحلي']]],
            ['sku' => 'PE-PL-001-XL-N', 'name' => 'XL - كحلي', 'price' => 340, 'quantity' => 15, 'attrs' => [['name' => 'المقاس', 'value' => 'XL'], ['name' => 'اللون', 'value' => 'كحلي']]],
            ['sku' => 'PE-PL-001-M-G', 'name' => 'M - رمادي', 'price' => 320, 'quantity' => 25, 'attrs' => [['name' => 'المقاس', 'value' => 'M'], ['name' => 'اللون', 'value' => 'رمادي']]],
            ['sku' => 'PE-PL-001-L-G', 'name' => 'L - رمادي', 'price' => 320, 'quantity' => 20, 'attrs' => [['name' => 'المقاس', 'value' => 'L'], ['name' => 'اللون', 'value' => 'رمادي']]],
            ['sku' => 'PE-PL-001-XL-G', 'name' => 'XL - رمادي', 'price' => 340, 'quantity' => 10, 'attrs' => [['name' => 'المقاس', 'value' => 'XL'], ['name' => 'اللون', 'value' => 'رمادي']]],
        ];
        foreach ($poloVariants as $v) {
            ProductVariant::firstOrCreate([
                'product_id' => $polo->id,
                'sku' => $v['sku'],
            ], [
                'name' => $v['name'],
                'price' => $v['price'],
                'discount_price' => null,
                'track_inventory' => true,
                'quantity' => $v['quantity'],
                'attributes' => $v['attrs'],
            ]);
        }
        $polo->update([
            'quantity' => ProductVariant::where('product_id', $polo->id)->sum('quantity'),
            'price' => ProductVariant::where('product_id', $polo->id)->min('price'),
        ]);

        // 3. Invitation Card
        $invitation = Product::firstOrCreate(['sku' => 'PE-IV-001'], [
            'category_id' => $this->catCards->id,
            'name' => 'كارت دعوة فاخر',
            'slug' => 'luxury-invitation-card',
            'description' => 'كروت دعوة فاخرة للمناسبات الخاصة.',
            'type' => 'variable',
            'price' => 300,
            'track_inventory' => true,
            'quantity' => 0,
            'is_active' => true,
        ]);
        
        $invVariants = [
            ['sku' => 'PE-IV-001-GLD', 'name' => 'ذهبي', 'price' => 300, 'quantity' => 100, 'attrs' => [['name' => 'اللون', 'value' => 'ذهبي']]],
            ['sku' => 'PE-IV-001-SLV', 'name' => 'فضي', 'price' => 300, 'quantity' => 80, 'attrs' => [['name' => 'اللون', 'value' => 'فضي']]],
            ['sku' => 'PE-IV-001-CPR', 'name' => 'نحاسي', 'price' => 320, 'quantity' => 50, 'attrs' => [['name' => 'اللون', 'value' => 'نحاسي']]],
        ];
        foreach ($invVariants as $v) {
            ProductVariant::firstOrCreate([
                'product_id' => $invitation->id,
                'sku' => $v['sku'],
            ], [
                'name' => $v['name'],
                'price' => $v['price'],
                'discount_price' => null,
                'track_inventory' => true,
                'quantity' => $v['quantity'],
                'attributes' => $v['attrs'],
            ]);
        }
        $invitation->update([
            'quantity' => ProductVariant::where('product_id', $invitation->id)->sum('quantity'),
            'price' => ProductVariant::where('product_id', $invitation->id)->min('price'),
        ]);

        // 4. Printed Bag
        $bag = Product::firstOrCreate(['sku' => 'PE-BG-001'], [
            'category_id' => $this->catGifts->id,
            'name' => 'حقيبة قماش مطبوعة',
            'slug' => 'printed-canvas-bag',
            'description' => 'حقائب قماشية مطبوعة صديقة للبيئة.',
            'type' => 'variable',
            'price' => 175,
            'track_inventory' => true,
            'quantity' => 0,
            'is_active' => true,
        ]);
        
        $bagVariants = [
            ['sku' => 'PE-BG-001-S', 'name' => 'صغير', 'price' => 175, 'quantity' => 60, 'attrs' => [['name' => 'المقاس', 'value' => 'صغير']]],
            ['sku' => 'PE-BG-001-M', 'name' => 'وسط', 'price' => 200, 'quantity' => 40, 'attrs' => [['name' => 'المقاس', 'value' => 'وسط']]],
            ['sku' => 'PE-BG-001-L', 'name' => 'كبير', 'price' => 230, 'quantity' => 25, 'attrs' => [['name' => 'المقاس', 'value' => 'كبير']]],
        ];
        foreach ($bagVariants as $v) {
            ProductVariant::firstOrCreate([
                'product_id' => $bag->id,
                'sku' => $v['sku'],
            ], [
                'name' => $v['name'],
                'price' => $v['price'],
                'discount_price' => null,
                'track_inventory' => true,
                'quantity' => $v['quantity'],
                'attributes' => $v['attrs'],
            ]);
        }
        $bag->update([
            'quantity' => ProductVariant::where('product_id', $bag->id)->sum('quantity'),
            'price' => ProductVariant::where('product_id', $bag->id)->min('price'),
        ]);

        $this->command->info('  → تم إنشاء 10 منتجات (6 بسيطة و 4 متغيرة) مع 20 متغير');
    }

    private function linkAddonsToCategories(): void
    {
        $this->catPaper->addonGroups()->syncWithoutDetaching([$this->wrapping->id]);
        $this->catGifts->addonGroups()->syncWithoutDetaching([$this->wrapping->id, $this->express->id]);
        $this->catCards->addonGroups()->syncWithoutDetaching([$this->namePrinting->id]);
        $this->catClothes->addonGroups()->syncWithoutDetaching([$this->namePrinting->id, $this->wrapping->id]);
        
        $this->command->info('  → تم ربط الخدمات بالتصنيفات');
    }
}
