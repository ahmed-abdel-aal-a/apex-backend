<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;

class ShippingCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'description', 'is_active', 'is_configured', 'cod_supported', 'credentials'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_configured' => 'boolean',
        'cod_supported' => 'boolean',
        'credentials' => AsEncryptedArrayObject::class,
    ];
}
