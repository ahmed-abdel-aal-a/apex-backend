<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => (float) $this->price,
            'discount_price' => $this->discount_price ? (float) $this->discount_price : null,
            'sku' => $this->sku,
            'track_inventory' => $this->track_inventory,
            'in_stock' => !$this->track_inventory || (int) $this->available_quantity > 0,
            'quantity' => (int) ($this->quantity ?? 0),
            'available_quantity' => (int) ($this->available_quantity ?? 0),
            'attributes' => $this->attributes,
        ];
    }
}
