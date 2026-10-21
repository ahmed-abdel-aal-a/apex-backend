<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Offer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'image_path',
        'linkable_type',
        'linkable_id',
        'custom_url',
        'is_active',
        'start_date',
        'end_date',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'image_path' => 'array',
            'is_active' => 'boolean',
            'start_date' => 'datetime:UTC',
            'end_date' => 'datetime:UTC',
        ];
    }

    public function linkable()
    {
        return $this->morphTo();
    }
}
