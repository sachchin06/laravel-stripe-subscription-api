<?php

namespace App\Domains\Subscription\Services;

use App\Enums\BillingInterval;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service for managing subscription plans and pricing
 */
class PlanService
{
    public function getAllPlansWithPrices(): Collection
    {
        return Plan::with('prices')->get();
    }

    public function findBySlug(string $slug): ?Plan
    {
        return Plan::where('slug', $slug)->with('prices')->first();
    }

    public function findPriceByStripeId(string $stripePriceId): ?PlanPrice
    {
        return PlanPrice::where('stripe_price_id', $stripePriceId)
            ->with('plan')
            ->first();
    }

    public function findById(int $planId): ?Plan
    {
        return Plan::with('prices')->find($planId);
    }

    public function exists(int $planId): bool
    {
        return Plan::where('id', $planId)->exists();
    }

    public function getMonthlyPrice(Plan $plan): ?PlanPrice
    {
        return $plan->prices()->where('interval', BillingInterval::MONTH)->first();
    }

    public function getYearlyPrice(Plan $plan): ?PlanPrice
    {
        return $plan->prices()->where('interval', BillingInterval::YEAR)->first();
    }
}