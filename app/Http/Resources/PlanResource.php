<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'stripe_product_id' => $this->stripe_product_id,
            'is_active' => $this->is_active,
            'prices' => PlanPriceResource::collection($this->whenLoaded('prices')),
            'created_at' => $this->created_at,
        ];
    }
}
