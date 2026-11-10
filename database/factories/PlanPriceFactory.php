<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'stripe_price_id' => 'price_'.Str::random(14),
            'price' => 2000,
            'currency' => 'usd',
            'interval' => BillingInterval::MONTH,
        ];
    }

    public function yearly(): static
    {
        return $this->state(fn () => ['interval' => BillingInterval::YEAR]);
    }

    public function notInStripe(): static
    {
        return $this->state(fn () => ['stripe_price_id' => null]);
    }
}
