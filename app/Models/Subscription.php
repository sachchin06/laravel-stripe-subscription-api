<?php

namespace App\Models;

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
     * Check if subscription is active (including grace period)
     */
    public function isActive(): bool
    {
        return in_array($this->stripe_status, ['active', 'trialing']) || $this->isOnGracePeriod();
    }

    /**
     * Check if subscription is canceled
     */
    public function isCanceled(): bool
    {
        return $this->stripe_status === 'canceled';
    }

    /**
     * Check if subscription is on grace period
     */
    public function isOnGracePeriod(): bool
    {
        return $this->stripe_status === 'canceled' && 
               $this->ends_at && 
               $this->ends_at->isFuture();
    }

    /**
     * Check if subscription has expired
     */
    public function hasExpired(): bool
    {
        return $this->stripe_status === 'canceled' && 
               $this->ends_at && 
               $this->ends_at->isPast();
    }

    /**
     * Get days remaining in grace period
     */
    public function gracePeriodDaysRemaining(): int
    {
        if (!$this->isOnGracePeriod()) {
            return 0;
        }

        return max(0, now()->diffInDays($this->ends_at, false));
    }

    public function scopeActive($query)
    {
        return $query->whereIn('stripe_status', ['active', 'trialing']);
    }

    public function scopeCanceled($query)
    {
        return $query->where('stripe_status', 'canceled');
    }
}
