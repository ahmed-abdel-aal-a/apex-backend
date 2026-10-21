<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'session_id' => $this->session_id,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'status' => $this->status,
            'total_amount' => (float) ($this->total_amount ?: ($this->relationLoaded('items') ? $this->items->sum(fn($i) => (float)$i->price * (int)$i->quantity) : 0)),
            'coupon_id' => $this->coupon_id,
            'discount_amount' => (float) ($this->discount_amount ?? 0),
            'coupon' => $this->coupon ? [
                'id' => $this->coupon->id,
                'code' => $this->coupon->code,
                'type' => $this->coupon->type,
                'value' => (float) $this->coupon->value,
                'min_order_amount' => $this->coupon->min_order_amount !== null ? (float)$this->coupon->min_order_amount : null,
                'max_discount_amount' => $this->coupon->max_discount_amount !== null ? (float)$this->coupon->max_discount_amount : null,
                'apply_to_sale_items' => (bool) $this->coupon->apply_to_sale_items,
            ] : null,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'discount' => $this->whenLoaded('discount'),
        ];
    }
}
