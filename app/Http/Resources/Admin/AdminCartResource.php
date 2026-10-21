<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminCartResource extends JsonResource
{
    public function toArray($request)
    {
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
        $recoveryUrl = rtrim($frontendUrl, '/') . '/recover?cart_uuid=' . $this->uuid;

        return [
            'id' => $this->id,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'customer_type' => $this->customer_type,
            'total_amount' => $this->total_amount,
            'abandoned_at' => $this->abandoned_at,
            'status' => $this->status,
            'recovery_url' => $recoveryUrl,
            'has_push_permission' => $this->has_push_permission,
            'items' => $this->whenLoaded('items'),
            'discount' => $this->whenLoaded('discount'),
            'contact_history' => $this->whenLoaded('contactHistory'),
        ];
    }
}
