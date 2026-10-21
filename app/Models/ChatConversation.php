<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'uuid', 'is_online', 'unread_count'
    ];

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')->oldest();
    }
}
