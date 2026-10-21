<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cart_id', 'customer_id', 'customer_name', 'customer_phone', 'total', 'items_count',
        'status', 'payment_status', 'shipping_company',
        'tracking_number', 'shipping_address',
        'zone_id', 'subtotal', 'shipping_cost', 'shipping_method',
        'cod_fee', 'tax_amount', 'payment_method',
        'coupon_id', 'coupon_code', 'discount_amount',
        'payment_type', 'deposit_percentage', 'deposit_amount', 'remaining_amount',
        'deposit_paid_at', 'remaining_paid_at', 'inventory_deducted_at',
        'payment_token', 'payment_token_expires_at', 'price_confirmed_at',
        'price_expires_at', 'payment_session_expires_at', 'admin_price_notes'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'cod_fee' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'deposit_percentage' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'deposit_paid_at' => 'datetime',
        'remaining_paid_at' => 'datetime',
        'inventory_deducted_at' => 'datetime',
        'payment_token_expires_at' => 'datetime',
        'price_confirmed_at' => 'datetime',
        'price_expires_at' => 'datetime',
        'payment_session_expires_at' => 'datetime',
    ];

    protected $appends = ['order_number'];

    protected function orderNumber(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->id ? ('#ORD-' . str_pad((string)$this->id, 4, '0', STR_PAD_LEFT)) : null,
        );
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function couponUsage()
    {
        return $this->hasOne(CouponUsage::class);
    }
}
