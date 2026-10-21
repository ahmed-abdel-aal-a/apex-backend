<?php
namespace App\Http\Resources\Storefront;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddonGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'input_type' => $this->input_type,
            'price_multiplier_type' => $this->price_multiplier_type,
            'is_required' => $this->is_required,
            'addons' => ProductAddonResource::collection($this->whenLoaded('addons')),
        ];
    }
}
