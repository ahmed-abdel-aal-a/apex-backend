<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category_id',
        'type',
        'price',
        'discount_price',
        'sku',
        'barcode',
        'track_inventory',
        'is_active',
        'quantity',
        'weight',
        'seo_title',
        'seo_description',
        'meta_keywords',
        'canonical_url',
        'og_image',
        'is_indexable',
        'schema_markup',
        'images'
    ];

    protected function casts(): array
    {
        return [
            'track_inventory' => 'boolean',
            'is_active' => 'boolean',
            'is_indexable' => 'boolean',
            'schema_markup' => 'array',
            'images' => 'array',
        ];
    }

    protected $appends = ['available_quantity'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockReservations()
    {
        return $this->hasMany(\App\Models\StockReservation::class, 'product_id');
    }

    public function getAvailableQuantityAttribute()
    {
        if (!$this->track_inventory) return 999999;
        
        $reserved = $this->stockReservations
            ->where('expires_at', '>', now())
            ->sum('quantity');

        return max(0, $this->quantity - $reserved);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function addonGroups()
    {
        return $this->belongsToMany(AddonGroup::class, 'addon_group_product');
    }

    public function syncWithVariants()
    {
        if ($this->type === 'variable') {
            $allVariants = $this->variants()->get();
            
            if ($allVariants->isEmpty()) {
                return;
            }

            $pricedVariants = $allVariants->where('price', '>', 0);
            $minPrice = $pricedVariants->isNotEmpty() ? $pricedVariants->min('price') : $this->price;
            $minDiscount = $pricedVariants->isNotEmpty() ? $pricedVariants->min('discount_price') : $this->discount_price;
            $totalQuantity = $allVariants->sum('quantity') ?? 0;

            $this->updateQuietly([
                'price' => $minPrice,
                'discount_price' => $minDiscount,
                'quantity' => $totalQuantity,
            ]);

            $this->clearStorefrontCache();
        }
    }

    public static function bumpProductsCacheVersion(): int
    {
        $version = (int) \Illuminate\Support\Facades\Cache::get('products_cache_version', 1) + 1;
        \Illuminate\Support\Facades\Cache::forever('products_cache_version', $version);
        return $version;
    }

    public function clearStorefrontCache(): void
    {
        self::bumpProductsCacheVersion();
        \Illuminate\Support\Facades\Cache::forget("storefront_product_{$this->id}");
        if (!empty($this->slug)) {
            \Illuminate\Support\Facades\Cache::forget("storefront_product_{$this->slug}");
        }
    }

    protected static function booted()
    {
        static::saved(function ($model) {
            $model->clearStorefrontCache();
        });

        static::deleted(function ($model) {
            $model->clearStorefrontCache();
        });
    }
}
