<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'type', 'is_active', 'is_configured', 'credentials'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_configured' => 'boolean',
        'type' => 'array',
        'credentials' => AsEncryptedArrayObject::class,
    ];
}
