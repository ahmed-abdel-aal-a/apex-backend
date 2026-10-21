<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $addonGroups = null;
        if ($this->relationLoaded('addonGroups') || ($this->relationLoaded('category') && $this->category && $this->category->relationLoaded('addonGroups'))) {
            $productGroups = $this->relationLoaded('addonGroups') ? $this->addonGroups : collect();
            $categoryGroups = ($this->relationLoaded('category') && $this->category) ? ($this->category->addonGroups ?? collect()) : collect();
            
            $mergedGroups = $productGroups->merge($categoryGroups)
                ->where('is_active', true)
                ->unique('id')
                ->values();

            // We need to ensure addons are loaded on the groups
            $mergedGroups->loadMissing(['addons' => function ($q) {
                $q->where('is_active', true);
            }]);

            // فلترة الخدمات النشطة داخل كل مجموعة واستبعاد المجموعات الخالية
            $activeGroups = $mergedGroups->filter(function ($group) {
                if ($group->relationLoaded('addons')) {
                    $group->setRelation('addons', $group->addons->where('is_active', true)->values());
                }
                return $group->is_active && $group->addons->isNotEmpty();
            })->values();
            
            $addonGroups = AddonGroupResource::collection($activeGroups);
        }

        // Canonical URL generation (Always Absolute URL)
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
            $canonicalUrl = "{$frontendUrl}/product/{$slugOrId}";
        }

        // OG Image generation (Always Absolute URL)
        $ogImage = null;
        $rawImage = $this->og_image;
        if (empty($rawImage) && is_array($this->images) && !empty($this->images)) {
            $rawImage = $this->images[0];
        }

        if (!empty($rawImage)) {
            if (str_starts_with($rawImage, 'http://') || str_starts_with($rawImage, 'https://')) {
                $ogImage = $rawImage;
            } elseif (str_starts_with($rawImage, '//')) {
                $ogImage = 'https:' . $rawImage;
            } elseif (str_starts_with($rawImage, 'storage/') || str_starts_with($rawImage, '/storage/')) {
                $ogImage = url(ltrim($rawImage, '/'));
            } else {
                $ogImage = url('storage/' . ltrim($rawImage, '/'));
            }
        }

        // Schema Markup formatting (Ensure Array/Object, Prevent Double Encoding)
        $schemaMarkup = $this->schema_markup;
        if (is_string($schemaMarkup)) {
            $decoded = json_decode($schemaMarkup, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $schemaMarkup = $decoded;
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'type' => $this->type,
            'price' => (float) $this->price,
            'discount_price' => $this->discount_price ? (float) $this->discount_price : null,
            'sku' => $this->sku,
            'track_inventory' => $this->track_inventory,
            'in_stock' => !$this->track_inventory || (int) $this->available_quantity > 0,
            'quantity' => (int) ($this->quantity ?? 0),
            'available_quantity' => (int) ($this->available_quantity ?? 0),
            'images' => $this->images,
            'image_url' => $ogImage,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'meta_keywords' => $this->meta_keywords,
            'canonical_url' => $canonicalUrl,
            'og_image' => $ogImage,
            'is_indexable' => $this->is_indexable !== null ? (bool) $this->is_indexable : true,
            'schema_markup' => $schemaMarkup,
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'available_addon_groups' => $addonGroups,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
