<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $fillable = [
        'manual_confirmation_required', 'whatsapp_template',
    ];

    protected $casts = [
        'manual_confirmation_required' => 'boolean',
    ];
}
