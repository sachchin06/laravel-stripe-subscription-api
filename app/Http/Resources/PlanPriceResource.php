<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'stripe_price_id' => $this->stripe_price_id,
            'price' => $this->price,
            'currency' => $this->currency,
            'interval' => $this->interval,
            'created_at' => $this->created_at,
        ];
    }
}
