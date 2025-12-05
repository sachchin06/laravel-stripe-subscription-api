<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'price' => new PlanPriceResource($this->whenLoaded('price')),
            'stripe_subscription_id' => $this->stripe_subscription_id,
            'stripe_customer_id' => $this->stripe_customer_id,
            'status' => $this->stripe_status,
            'trial_ends_at' => $this->trial_ends_at,
            'ends_at' => $this->ends_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
