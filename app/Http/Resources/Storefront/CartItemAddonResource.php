<?php
namespace App\Http\Resources\Storefront;

use Illuminate\Http\Resources\Json\JsonResource;

class CartItemAddonResource extends JsonResource
{
    public function toArray($request)
    {
        $previewUrls = [];
        $sessionId = $request->header('X-Session-ID') ?? session()->getId();

        if (!empty($this->temp_file_ids)) {
            foreach ($this->temp_file_ids as $id) {
                $previewUrls[] = url("/api/v1/storefront/addons/temp-file/{$id}?session_id={$sessionId}");
            }
        }

        return [
            'id' => $this->id,
            'cart_item_id' => $this->cart_item_id,
            'product_addon_id' => $this->product_addon_id,
            'temp_file_ids' => $this->temp_file_ids ?? [],
            'preview_urls' => $previewUrls,
            'addon' => new ProductAddonResource($this->whenLoaded('addon')),
        ];
    }
}
