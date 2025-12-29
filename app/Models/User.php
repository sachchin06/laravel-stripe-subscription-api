<?php

namespace App\Models;

use App\Domains\Subscription\Services\FeatureGateService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'stripe_customer_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get user's active subscription
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->with(['plan', 'price'])
            ->first();
    }

    /**
     * Check if user has active subscription
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->exists();
    }

    /**
     * Check if user has active subscription for specific price
     */
    public function hasActiveSubscriptionForPrice(string $stripePriceId): bool
    {
        return $this->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->whereHas('price', function ($query) use ($stripePriceId) {
                $query->where('stripe_price_id', $stripePriceId);
            })
            ->exists();
    }

    /**
     * Check if user is subscribed to specific plan
     */
    public function isSubscribedTo(string $planSlug): bool
    {
        return $this->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->whereHas('plan', function ($query) use ($planSlug) {
                $query->where('slug', $planSlug);
            })
            ->exists();
    }

    /**
     * Check if user can access a feature
     */
    public function canAccessFeature(string $featureSlug): bool
    {
        return app(FeatureGateService::class)->hasFeatureAccess($this, $featureSlug);
    }

    /**
     * Get user's current usage for an action type
     */
    public function getCurrentUsage(string $actionType): int
    {
        return app(FeatureGateService::class)->getCurrentUsage($this, $actionType);
    }

    /**
     * Get user's usage limit for an action type
     */
    public function getUsageLimit(string $actionType): ?int
    {
        return app(FeatureGateService::class)->getUsageLimit($this, $actionType);
    }
}
