<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_price_id' => PlanPrice::factory(),
            // Always the plan that the chosen price belongs to.
            'plan_id' => fn (array $attributes) => PlanPrice::find($attributes['plan_price_id'])->plan_id,
            'stripe_subscription_id' => 'sub_'.Str::random(14),
            'stripe_customer_id' => 'cus_'.Str::random(14),
            'stripe_status' => SubscriptionStatus::ACTIVE,
            'trial_ends_at' => null,
            'ends_at' => null,
        ];
    }

    public function forPrice(PlanPrice $price): static
    {
        return $this->state(fn () => ['plan_price_id' => $price->id, 'plan_id' => $price->plan_id]);
    }

    public function status(SubscriptionStatus $status): static
    {
        return $this->state(fn () => ['stripe_status' => $status]);
    }

    public function canceled(): static
    {
        return $this->status(SubscriptionStatus::CANCELED);
    }
}
