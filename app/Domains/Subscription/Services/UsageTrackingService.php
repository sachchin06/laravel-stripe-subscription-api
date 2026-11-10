<?php

namespace App\Domains\Subscription\Services;

use App\Models\UsageRecord;
use App\Models\User;
use Carbon\Carbon;

/**
 * Service for tracking and managing user feature usage
 */
class UsageTrackingService
{
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

    public function getCurrentMonthUsage(User $user, string $feature): int
    {
        return UsageRecord::where('user_id', $user->id)
            ->forFeature($feature)
            ->currentMonth()
            ->sum('amount');
    }

    public function getUsageInPeriod(User $user, string $feature, Carbon $start, Carbon $end): int
    {
        return UsageRecord::where('user_id', $user->id)
            ->forFeature($feature)
            ->inPeriod($start, $end)
            ->sum('amount');
    }

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
     * The user's monthly limit for a feature; null means unlimited.
     *
     * In config/features.php a null limit deliberately means "unlimited", so a
     * missing entry must not be read as null. A plan (or a plan's feature) that
     * is not configured, e.g. one created through the products API, falls back
     * to the free-tier limit.
     */
    public function getUserLimit(User $user, string $feature): ?int
    {
        $freeLimit = config("features.limits.free.{$feature}");
        $planLimits = config('features.limits.plans.'.$user->activeSubscription()?->plan->slug);

        if (! is_array($planLimits) || ! array_key_exists($feature, $planLimits)) {
            return $freeLimit;
        }

        return $planLimits[$feature];
    }

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
