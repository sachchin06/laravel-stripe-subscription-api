<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'plan_price_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'stripe_status',
        'trial_ends_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'stripe_status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'plan_price_id');
    }

    /**
     * Active or trialing, or cancelled but still inside the paid period.
     */
    public function isActive(): bool
    {
        return $this->stripe_status?->isActive() || $this->isOnGracePeriod();
    }

    public function isCanceled(): bool
    {
        return $this->stripe_status === SubscriptionStatus::CANCELED;
    }

    public function isOnGracePeriod(): bool
    {
        return $this->isCanceled() && $this->ends_at?->isFuture();
    }

    public function hasExpired(): bool
    {
        return $this->isCanceled() && $this->ends_at?->isPast();
    }

    public function gracePeriodDaysRemaining(): int
    {
        if (! $this->isOnGracePeriod()) {
            return 0;
        }

        return (int) max(0, now()->diffInDays($this->ends_at, false));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('stripe_status', SubscriptionStatus::activeStatuses());
    }

    public function scopeCanceled(Builder $query): Builder
    {
        return $query->where('stripe_status', SubscriptionStatus::CANCELED);
    }
}
