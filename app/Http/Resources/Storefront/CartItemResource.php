<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray($request)
    {
        if ($this->item_type === 'printing') {
            return [
                'id' => $this->id,
                'cart_id' => $this->cart_id,
                'item_type' => $this->item_type,
                'printing_type_id' => $this->printing_type_id,
                'custom_product_name' => $this->custom_product_name,
                'price' => (float) $this->price,
                'quantity' => $this->quantity,
                'printing_options' => $this->printing_options,
                'uploaded_files' => $this->uploaded_files,
                'file_status' => $this->file_status,
                'quote_expires_at' => $this->quote_expires_at,
                'printingType' => $this->whenLoaded('printingType'),
                'addons' => CartItemAddonResource::collection($this->whenLoaded('addons')),
            ];
        }

        return [
            'id' => $this->id,
            'cart_id' => $this->cart_id,
            'item_type' => $this->item_type,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'price' => (float) $this->price,
            'quantity' => $this->quantity,
            'is_sale_item' => $this->is_sale_item,
            'product' => new ProductResource($this->whenLoaded('product')),
            'productVariant' => new ProductVariantResource($this->whenLoaded('productVariant')),
            'addons' => CartItemAddonResource::collection($this->whenLoaded('addons')),
        ];
    }
}
