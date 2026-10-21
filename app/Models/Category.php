<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'parent_id', 'is_active', 'slug', 'seo_title', 'seo_description',
        'meta_keywords', 'canonical_url', 'og_image', 'is_indexable'
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_indexable' => 'boolean',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function addonGroups()
    {
        return $this->belongsToMany(AddonGroup::class, 'addon_group_category');
    }

    protected static function booted()
    {
        static::saved(function ($model) {
            Product::bumpProductsCacheVersion();
        });

        static::deleted(function ($model) {
            Product::bumpProductsCacheVersion();
        });
    }
}
