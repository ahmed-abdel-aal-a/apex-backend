<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'category',
        'status',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'og_image',
        'is_indexable',
        'schema_markup',
        'featured_image',
        'video',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'is_indexable' => 'boolean',
            'schema_markup' => 'array',
        ];
    }
}
