<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FaqQuestion extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'section_id', 'question', 'answer', 'is_active', 'order'
    ];

    public function section()
    {
        return $this->belongsTo(FaqSection::class);
    }
}
