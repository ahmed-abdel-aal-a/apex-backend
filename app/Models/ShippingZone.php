<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'delivery_days', 'rate'];

    protected $casts = [
        'rate' => 'float',
    ];

    protected $appends = ['cost'];

    public function getCostAttribute(): float
    {
        return (float) ($this->attributes['rate'] ?? 0);
    }
}
