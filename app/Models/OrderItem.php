<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'name', 'quantity', 'price_at_purchase',
        'variant_id', 'total', // [V14 Bugfix] added missing fields
        'item_type', 'printing_type_id', 'custom_product_name', 'printing_options',
        'uploaded_files', 'file_status', 'file_rejection_reason', 'estimated_delivery_date',
        'tax_basis_points_snapshot', 'source_order_item_id',
        'requires_price_confirmation', 'price_confirmation_status',
        'original_line_total', 'confirmed_line_total', 'line_difference', 'admin_notes'
    ];

    protected $casts = [
        'printing_options' => 'array',
        'uploaded_files' => 'array',
        'estimated_delivery_date' => 'date',
        'requires_price_confirmation' => 'boolean',
        'original_line_total' => 'decimal:2',
        'confirmed_line_total' => 'decimal:2',
        'line_difference' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function printingType()
    {
        return $this->belongsTo(PrintingType::class);
    }

    public function addons()
    {
        return $this->hasMany(OrderItemAddon::class);
    }

    public function sourceOrderItem()
    {
        return $this->belongsTo(OrderItem::class, 'source_order_item_id');
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
