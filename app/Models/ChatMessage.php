<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id', 'text', 'sender', 'is_image'
    ];

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class);
    }
}
