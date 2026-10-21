<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrowsingHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'title' => $this->title,
            'timestamp' => $this->visited_at ? $this->visited_at->toIso8601String() : $this->created_at->toIso8601String(),
            'type' => $this->type,
        ];
    }
}
