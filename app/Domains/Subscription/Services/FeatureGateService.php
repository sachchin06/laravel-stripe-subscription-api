<?php

namespace App\Domains\Subscription\Services;

use App\Models\User;
use App\Models\Plan;

/**
 * Service for managing feature access based on subscription plans
 * 
 * This service provides a centralized way to check feature access
 * and enforce plan-based limitations.
 */
class FeatureGateService
{
    public function __construct(
        private readonly UsageTrackingService $usageTrackingService
    ) {}

    /**
     * Check if user has access to a specific feature
     */
    public function hasFeatureAccess(User $user, string $featureSlug): bool
    {
        $subscription = $user->activeSubscription();
        
        if (!$subscription || !$subscription->isActive()) {
            return $this->isFeatureFree($featureSlug);
        }

        return $this->planHasFeature($subscription->plan, $featureSlug);
    }

    /**
     * Check if user can perform an action based on usage limits
     */
    public function canPerformAction(User $user, string $actionType, int $requestedAmount = 1): bool
    {
        return $this->usageTrackingService->canPerformAction($user, $actionType, $requestedAmount);
    }

    /**
     * Record usage for a user action
     */
    public function recordUsage(User $user, string $actionType, int $amount = 1, array $metadata = []): void
    {
        $this->usageTrackingService->recordUsage($user, $actionType, $amount, $metadata);
    }

    /**
     * Get user's current usage for a specific action type
     */
    public function getCurrentUsage(User $user, string $actionType): int
    {
        return $this->usageTrackingService->getCurrentMonthUsage($user, $actionType);
    }

    /**
     * Get user's usage limit for a specific action type
     */
    public function getUsageLimit(User $user, string $actionType): ?int
    {
        return $this->usageTrackingService->getUserLimit($user, $actionType);
    }

    /**
     * Get comprehensive usage summary for user
     */
    public function getUsageSummary(User $user): array
    {
        return $this->usageTrackingService->getUsageSummary($user);
    }

    /**
     * Check if a feature is available in the free tier
     */
    private function isFeatureFree(string $featureSlug): bool
    {
        $freeFeatures = config('features.free', []);
        return in_array($featureSlug, $freeFeatures);
    }

    /**
     * Check if a plan has access to a specific feature
     */
    private function planHasFeature(Plan $plan, string $featureSlug): bool
    {
        $planFeatures = config("features.plans.{$plan->slug}", []);
        return in_array($featureSlug, $planFeatures);
    }
}