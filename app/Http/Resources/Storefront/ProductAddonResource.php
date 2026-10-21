<?php
namespace App\Http\Resources\Storefront;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductAddonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'price' => (float) $this->price,
            'price_type' => $this->price_type,
            'requires_upload' => $this->requires_upload,
            'allow_multiple_uploads' => $this->allow_multiple_uploads,
            'max_uploads' => $this->max_uploads,
            'track_inventory' => $this->track_inventory,
            'quantity' => $this->quantity,
            'in_stock' => !$this->track_inventory || $this->quantity > 0,
        ];
    }
}
