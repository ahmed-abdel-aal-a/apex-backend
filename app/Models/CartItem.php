<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory; // [V14 Architecture Fix]

class CartItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_sale_item' => 'boolean',
        'printing_options' => 'array',
        'uploaded_files' => 'array',
        'quote_expires_at' => 'datetime',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function printingType()
    {
        return $this->belongsTo(PrintingType::class);
    }

    public function addons()
    {
        return $this->hasMany(CartItemAddon::class);
    }
}
