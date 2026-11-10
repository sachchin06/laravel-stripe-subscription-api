<?php

namespace App\Domains\Subscription\Services;

use App\Models\Plan;
use App\Models\User;

/**
 * Service for managing feature access based on subscription plans
 *
 * This service provides a centralised way to check feature access
 * and enforce plan-based limitations.
 */
class FeatureGateService
{
    public function __construct(
        private readonly UsageTrackingService $usageTrackingService
    ) {}

    public function hasFeatureAccess(User $user, string $featureSlug): bool
    {
        $subscription = $user->activeSubscription();

        if (! $subscription || ! $subscription->isActive()) {
            return $this->isFeatureFree($featureSlug);
        }

        return $this->planHasFeature($subscription->plan, $featureSlug);
    }

    public function canPerformAction(User $user, string $actionType, int $requestedAmount = 1): bool
    {
        return $this->usageTrackingService->canPerformAction($user, $actionType, $requestedAmount);
    }

    public function recordUsage(User $user, string $actionType, int $amount = 1, array $metadata = []): void
    {
        $this->usageTrackingService->recordUsage($user, $actionType, $amount, $metadata);
    }

    public function getCurrentUsage(User $user, string $actionType): int
    {
        return $this->usageTrackingService->getCurrentMonthUsage($user, $actionType);
    }

    public function getUsageLimit(User $user, string $actionType): ?int
    {
        return $this->usageTrackingService->getUserLimit($user, $actionType);
    }

    public function getUsageSummary(User $user): array
    {
        return $this->usageTrackingService->getUsageSummary($user);
    }

    private function isFeatureFree(string $featureSlug): bool
    {
        $freeFeatures = config('features.free', []);

        return in_array($featureSlug, $freeFeatures);
    }

    /**
     * A plan missing from config/features.php (e.g. one created through the
     * products API) gets the free tier's features, the same fallback
     * UsageTrackingService uses for limits.
     */
    private function planHasFeature(Plan $plan, string $featureSlug): bool
    {
        $planFeatures = config("features.plans.{$plan->slug}");

        if (! is_array($planFeatures)) {
            return $this->isFeatureFree($featureSlug);
        }

        return in_array($featureSlug, $planFeatures, true);
    }
}
