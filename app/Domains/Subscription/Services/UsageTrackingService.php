<?php

namespace App\Domains\Subscription\Services;

use App\Models\User;
use App\Models\UsageRecord;
use Carbon\Carbon;

/**
 * Service for tracking and managing user feature usage
 */
class UsageTrackingService
{
    /**
     * Record usage for a user and feature
     */
    public function recordUsage(User $user, string $feature, int $amount = 1, array $metadata = []): UsageRecord
    {
        return UsageRecord::create([
            'user_id' => $user->id,
            'feature' => $feature,
            'amount' => $amount,
            'metadata' => $metadata,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Get current month usage for a user and feature
     */
    public function getCurrentMonthUsage(User $user, string $feature): int
    {
        return UsageRecord::where('user_id', $user->id)
            ->forFeature($feature)
            ->currentMonth()
            ->sum('amount');
    }

    /**
     * Get usage for a specific period
     */
    public function getUsageInPeriod(User $user, string $feature, Carbon $start, Carbon $end): int
    {
        return UsageRecord::where('user_id', $user->id)
            ->forFeature($feature)
            ->inPeriod($start, $end)
            ->sum('amount');
    }

    /**
     * Check if user can perform action based on usage limits
     */
    public function canPerformAction(User $user, string $feature, int $requestedAmount = 1): bool
    {
        $currentUsage = $this->getCurrentMonthUsage($user, $feature);
        $limit = $this->getUserLimit($user, $feature);

        // No limit means unlimited
        if ($limit === null) {
            return true;
        }

        return ($currentUsage + $requestedAmount) <= $limit;
    }

    /**
     * Get user's limit for a feature based on their subscription
     */
    public function getUserLimit(User $user, string $feature): ?int
    {
        $subscription = $user->activeSubscription();
        
        if (!$subscription) {
            return config("features.limits.free.{$feature}");
        }

        return config("features.limits.plans.{$subscription->plan->slug}.{$feature}");
    }

    /**
     * Get usage summary for a user
     */
    public function getUsageSummary(User $user): array
    {
        $features = ['api_calls', 'storage_mb', 'team_members'];
        $summary = [];

        foreach ($features as $feature) {
            $current = $this->getCurrentMonthUsage($user, $feature);
            $limit = $this->getUserLimit($user, $feature);
            
            $summary[$feature] = [
                'current' => $current,
                'limit' => $limit,
                'percentage' => $limit ? round(($current / $limit) * 100, 2) : 0,
                'unlimited' => $limit === null,
            ];
        }

        return $summary;
    }

    /**
     * Get usage history for a user and feature
     */
    public function getUsageHistory(User $user, string $feature, int $days = 30): array
    {
        $records = UsageRecord::where('user_id', $user->id)
            ->forFeature($feature)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->orderBy('recorded_at')
            ->get()
            ->groupBy(function ($record) {
                return $record->recorded_at->format('Y-m-d');
            })
            ->map(function ($dayRecords) {
                return $dayRecords->sum('amount');
            });

        return $records->toArray();
    }
}