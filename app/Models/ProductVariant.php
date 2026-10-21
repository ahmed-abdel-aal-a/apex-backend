<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'price',
        'discount_price',
        'sku',
        'track_inventory',
        'quantity',
        'attributes'
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'track_inventory' => 'boolean',
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    protected $appends = ['available_quantity'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockReservations()
    {
        return $this->hasMany(\App\Models\StockReservation::class, 'product_variant_id');
    }

    public function getAvailableQuantityAttribute()
    {
        // Variant assumes parent product tracks inventory if variant does.
        $reserved = $this->stockReservations
            ->where('expires_at', '>', now())
            ->sum('quantity');

        return max(0, $this->quantity - $reserved);
    }

    public function clearStorefrontCache(): void
    {
        if ($this->relationLoaded('product') && $this->product) {
            $this->product->clearStorefrontCache();
        } else {
            $prod = \App\Models\Product::find($this->product_id);
            if ($prod) {
                $prod->clearStorefrontCache();
            } else {
                \App\Models\Product::bumpProductsCacheVersion();
                \Illuminate\Support\Facades\Cache::forget("storefront_product_{$this->product_id}");
            }
        }
    }

    protected static function booted()
    {
        static::saved(function ($model) {
            $model->clearStorefrontCache();
            if ($model->product) {
                $model->product->syncWithVariants();
            }
        });

        static::deleted(function ($model) {
            $model->clearStorefrontCache();
            if ($model->product) {
                $model->product->syncWithVariants();
            }
        });
    }
}
