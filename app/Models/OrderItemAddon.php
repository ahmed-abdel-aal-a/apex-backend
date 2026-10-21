<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemAddon extends Model
{
    protected $fillable = [
        'order_item_id',
        'product_addon_id',
        'name_snapshot',
        'price_snapshot',
        'price_type_snapshot',
        'quantity',
        'options_snapshot',
        'disk',
        'file_paths',
    ];

    protected $casts = [
        'name_snapshot' => 'array',
        'price_snapshot' => 'decimal:2',
        'quantity' => 'integer',
        'options_snapshot' => 'array',
        'file_paths' => 'array',
    ];

    protected $appends = ['name', 'price_at_purchase', 'price_type', 'total'];

    public function getNameAttribute()
    {
        if (!isset($this->attributes['name_snapshot'])) return null;
        $val = $this->attributes['name_snapshot'];
        return is_string($val) ? json_decode($val, true) : $val;
    }

    public function getPriceAtPurchaseAttribute()
    {
        return isset($this->attributes['price_snapshot']) ? (float)$this->attributes['price_snapshot'] : 0.0;
    }

    public function getPriceTypeAttribute()
    {
        return $this->attributes['price_type_snapshot'] ?? 'fixed';
    }

    public function getTotalAttribute()
    {
        $price = isset($this->attributes['price_snapshot']) ? (float)$this->attributes['price_snapshot'] : 0.0;
        $qty = isset($this->attributes['quantity']) ? (int)$this->attributes['quantity'] : 1;
        return $price * $qty;
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function addon()
    {
        return $this->belongsTo(ProductAddon::class, 'product_addon_id');
    }
}
