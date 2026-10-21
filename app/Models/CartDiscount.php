<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartDiscount extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }
}
