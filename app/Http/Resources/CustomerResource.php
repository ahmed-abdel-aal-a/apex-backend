<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'ipAddress' => $this->ip_address,
            'source' => $this->source ?? 'direct',
            'totalOrders' => (int) $this->total_orders,
            'totalSpent' => (float) $this->total_spent,
            'lastSeen' => $this->last_seen ? $this->last_seen->toIso8601String() : null,
            'firstSeen' => $this->first_seen ? $this->first_seen->toIso8601String() : null,
            'visitsCount' => (int) ($this->visits_count ?? 1),
            'isGuest' => (bool) $this->is_guest,
            'device' => $this->device,
            'browsingHistory' => CustomerBrowsingHistoryResource::collection($this->whenLoaded('browsingHistory')),
        ];
    }
}
