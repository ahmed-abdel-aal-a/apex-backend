<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\AddonGroup;
use App\Models\ProductAddon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

class StorefrontTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Storefront Test Seeding (Phase 1 & 2)...');

        // ==========================================
        // Phase 1: Categories
        // ==========================================
        $catStationery = Category::firstOrCreate([
            'name' => 'أدوات مكتبية'
        ], [
            'is_active' => true
        ]);

        $catGifts = Category::firstOrCreate([
            'name' => 'هدايا مميزة'
        ], [
            'is_active' => true
        ]);

        $catPrints = Category::firstOrCreate([
            'name' => 'مطبوعات مخصصة'
        ], [
            'is_active' => true
        ]);

        $this->command->info('Categories seeded.');

        // ==========================================
        // Phase 1: Addon Groups & Addons
        // ==========================================
        $packagingGroup = AddonGroup::firstOrCreate([
            'name' => 'تغليف الهدايا',
            'input_type' => 'radio',
            'is_required' => false
        ]);

        ProductAddon::firstOrCreate([
            'addon_group_id' => $packagingGroup->id,
            'name' => 'تغليف عادي',
        ], [
            'price_type' => 'fixed',
            'price' => 20,
            'is_active' => true
        ]);

        ProductAddon::firstOrCreate([
            'addon_group_id' => $packagingGroup->id,
            'name' => 'تغليف فاخر (VIP)',
        ], [
            'price_type' => 'fixed',
            'price' => 50,
            'is_active' => true
        ]);

        $deliveryGroup = AddonGroup::firstOrCreate([
            'name' => 'خدمات التوصيل',
            'input_type' => 'checkbox',
            'is_required' => false
        ]);

        ProductAddon::firstOrCreate([
            'addon_group_id' => $deliveryGroup->id,
            'name' => 'توصيل مستعجل (نفس اليوم)',
        ], [
            'price_type' => 'fixed',
            'price' => 100,
            'is_active' => true
        ]);

        $this->command->info('Addon Groups and Addons seeded.');

        // ==========================================
        // Phase 2: Simple Product (طقم مكتب فاخر)
        // ==========================================
        $simpleProduct = Product::firstOrCreate([
            'sku' => 'ST-DESK-01'
        ], [
            'category_id' => $catStationery->id,
            'name' => 'طقم مكتب فاخر (جلد طبيعي)',
            'slug' => 'luxury-desk-set-leather',
            'description' => 'طقم مكتب متكامل مصنوع من الجلد الطبيعي الفاخر، يتكون من 5 قطع أساسية لتنظيم مكتبك بأناقة.',
            'type' => 'simple',
            'price' => 1500,
            'track_inventory' => true,
            'quantity' => 25,
            'is_active' => true,
        ]);

        // Attach addons to Simple Product
        $simpleProduct->addonGroups()->syncWithoutDetaching([$packagingGroup->id, $deliveryGroup->id]);

        $this->command->info('Simple Product seeded.');

        // ==========================================
        // Phase 2: Variable Product (تيشيرت بولو للشركات)
        // ==========================================
        $variableProduct = Product::firstOrCreate([
            'sku' => 'GFT-POLO-01'
        ], [
            'category_id' => $catGifts->id,
            'name' => 'تيشيرت بولو للشركات',
            'slug' => 'corporate-polo-shirt',
            'description' => 'تيشيرت بولو قطن 100% عالي الجودة، مناسب لهدايا الشركات أو الزي الموحد.',
            'type' => 'variable',
            'price' => 250, // Base price
            'track_inventory' => true,
            'is_active' => true,
        ]);

        // Create Variants (Sizes/Colors)
        $variants = [
            ['sku' => 'GFT-POLO-01-M-BLK', 'attributes' => json_encode(['size' => 'M', 'color' => 'Black']), 'qty' => 50, 'price' => 250],
            ['sku' => 'GFT-POLO-01-L-BLK', 'attributes' => json_encode(['size' => 'L', 'color' => 'Black']), 'qty' => 30, 'price' => 250],
            ['sku' => 'GFT-POLO-01-XL-BLK', 'attributes' => json_encode(['size' => 'XL', 'color' => 'Black']), 'qty' => 20, 'price' => 270], 
            ['sku' => 'GFT-POLO-01-M-WHT', 'attributes' => json_encode(['size' => 'M', 'color' => 'White']), 'qty' => 45, 'price' => 250],
            ['sku' => 'GFT-POLO-01-L-WHT', 'attributes' => json_encode(['size' => 'L', 'color' => 'White']), 'qty' => 10, 'price' => 250],
        ];

        foreach ($variants as $v) {
            ProductVariant::firstOrCreate([
                'product_id' => $variableProduct->id,
                'sku' => $v['sku']
            ], [
                'attributes' => $v['attributes'],
                'price' => $v['price'],
                'quantity' => $v['qty'],
                'track_inventory' => true
            ]);
        }
        
        // Update Total Available Quantity for the variable product
        $variableProduct->update([
            'quantity' => ProductVariant::where('product_id', $variableProduct->id)->sum('quantity')
        ]);

        // Attach addons to Variable Product
        $variableProduct->addonGroups()->syncWithoutDetaching([$packagingGroup->id, $deliveryGroup->id]);

        $this->command->info('Variable Product and Variants seeded.');
        $this->command->info('Phase 1 & 2 Seeding Completed Successfully! 🚀');
    }
}
