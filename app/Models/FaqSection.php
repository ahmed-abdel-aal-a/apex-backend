<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FaqSection extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'title', 'order'
    ];

    public function questions()
    {
        return $this->hasMany(FaqQuestion::class, 'section_id')->orderBy('order');
    }
}
