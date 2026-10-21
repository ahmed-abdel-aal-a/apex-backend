<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use App\Models\Traits\HasTenant;

class Cart extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'uuid',
        'store_id',
        'session_id',
        'customer_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_type',
        'ip_address',
        'user_agent',
        'status',
        'has_push_permission',
        'abandoned_at',
        'coupon_id',
        'discount_amount',
        'total_amount',
    ];

    protected $casts = [
        'abandoned_at' => 'datetime',
        'has_push_permission' => 'boolean',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function discount()
    {
        return $this->hasOne(CartDiscount::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function contactHistory()
    {
        return $this->hasMany(CartContactHistory::class);
    }

    public function calculateTotalAmount()
    {
        $this->updateQuietly(['total_amount' => $this->items()->sum(DB::raw('price * quantity'))]);
        return $this->total_amount;
    }
}
