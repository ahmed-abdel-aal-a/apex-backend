<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItemAddon extends Model
{
    protected $fillable = [
        'cart_item_id',
        'product_addon_id',
        'options_snapshot',
        'disk',
        'temp_file_ids',
        'file_paths',
    ];

    protected $casts = [
        'options_snapshot' => 'array',
        'temp_file_ids' => 'array',
        'file_paths' => 'array',
    ];

    public function cartItem()
    {
        return $this->belongsTo(CartItem::class);
    }

    public function addon()
    {
        return $this->belongsTo(ProductAddon::class, 'product_addon_id');
    }
}
