<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_message_enabled',
        'message_text',
        'display_location',
        'theme_color',
        'whatsapp_number',
        'whatsapp_enabled',
        'whatsapp_default_message',
    ];

    protected $casts = [
        'is_message_enabled' => 'boolean',
        'whatsapp_enabled' => 'boolean',
    ];
}
