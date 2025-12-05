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

    public function isActive(): bool
    {
        return in_array($this->stripe_status, ['active', 'trialing']);
    }

    public function isCanceled(): bool
    {
        return $this->stripe_status === 'canceled';
    }

    public function isOnGracePeriod(): bool
    {
        return $this->ends_at && $this->ends_at->isFuture();
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
