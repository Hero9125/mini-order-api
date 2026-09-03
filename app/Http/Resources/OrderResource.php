<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'status'       => $this->status,
            'total_amount' => (float) $this->total_amount,
            'notes'        => $this->notes,
            'items'        => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count'  => $this->whenLoaded('items', fn () => $this->items->count()),
            'user'         => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'created_at'   => $this->created_at->toIso8601String(),
            'updated_at'   => $this->updated_at->toIso8601String(),
        ];
    }
}
