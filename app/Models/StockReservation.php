<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_addon_id',
        'product_id',
        'product_variant_id',
        'order_id',
        'session_id',
        'user_id',
        'quantity',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function addon(): BelongsTo
    {
        return $this->belongsTo(ProductAddon::class, 'product_addon_id');
    }

    protected static function booted()
    {
        static::saved(function ($model) {
            \App\Models\Product::bumpProductsCacheVersion();
            if ($model->product_id) {
                \Illuminate\Support\Facades\Cache::forget("storefront_product_{$model->product_id}");
            }
        });

        static::deleted(function ($model) {
            \App\Models\Product::bumpProductsCacheVersion();
            if ($model->product_id) {
                \Illuminate\Support\Facades\Cache::forget("storefront_product_{$model->product_id}");
            }
        });
    }
}
