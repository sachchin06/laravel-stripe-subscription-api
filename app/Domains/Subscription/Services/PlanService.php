<?php

namespace App\Domains\Subscription\Services;

use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service for managing subscription plans and pricing
 */
class PlanService
{
    /**
     * Get all available plans with their prices
     */
    public function getAllPlansWithPrices(): Collection
    {
        return Plan::with('prices')->get();
    }

    /**
     * Find a plan by slug
     */
    public function findBySlug(string $slug): ?Plan
    {
        return Plan::where('slug', $slug)->with('prices')->first();
    }

    /**
     * Find a plan price by Stripe price ID
     */
    public function findPriceByStripeId(string $stripePriceId): ?PlanPrice
    {
        return PlanPrice::where('stripe_price_id', $stripePriceId)
            ->with('plan')
            ->first();
    }

    /**
     * Get plan by ID with prices
     */
    public function findById(int $planId): ?Plan
    {
        return Plan::with('prices')->find($planId);
    }

    /**
     * Check if a plan exists
     */
    public function exists(int $planId): bool
    {
        return Plan::where('id', $planId)->exists();
    }

    /**
     * Get monthly price for a plan
     */
    public function getMonthlyPrice(Plan $plan): ?PlanPrice
    {
        return $plan->prices()->where('interval', 'month')->first();
    }

    /**
     * Get yearly price for a plan
     */
    public function getYearlyPrice(Plan $plan): ?PlanPrice
    {
        return $plan->prices()->where('interval', 'year')->first();
    }
}