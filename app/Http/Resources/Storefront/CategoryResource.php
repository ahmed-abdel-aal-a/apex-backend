<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $frontendUrl = rtrim(config('app.frontend_url') ?: env('FRONTEND_URL', 'https://printexpress.com'), '/');
        $canonicalUrl = null;
        if (!empty($this->canonical_url)) {
            if (str_starts_with($this->canonical_url, 'http://') || str_starts_with($this->canonical_url, 'https://')) {
                $canonicalUrl = $this->canonical_url;
            } else {
                $canonicalUrl = $frontendUrl . '/' . ltrim($this->canonical_url, '/');
            }
        } else {
            $slugOrId = $this->slug ?: $this->id;
            $canonicalUrl = "{$frontendUrl}/shop?category_id={$slugOrId}";
        }

        $ogImage = null;
        if (!empty($this->og_image)) {
            if (str_starts_with($this->og_image, 'http://') || str_starts_with($this->og_image, 'https://')) {
                $ogImage = $this->og_image;
            } elseif (str_starts_with($this->og_image, '//')) {
                $ogImage = 'https:' . $this->og_image;
            } elseif (str_starts_with($this->og_image, 'storage/') || str_starts_with($this->og_image, '/storage/')) {
                $ogImage = url(ltrim($this->og_image, '/'));
            } else {
                $ogImage = url('storage/' . ltrim($this->og_image, '/'));
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'is_active' => (bool) $this->is_active,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'meta_keywords' => $this->meta_keywords,
            'canonical_url' => $canonicalUrl,
            'og_image' => $ogImage,
            'is_indexable' => (bool) ($this->is_indexable ?? true),
        ];
    }
}
