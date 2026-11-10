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
    use HasApiTokens, HasFactory, Notifiable;

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
            // Deliberately not in $fillable, so it can never be set from request data.
            'is_admin' => 'boolean',
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

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->active()
            ->with(['plan', 'price'])
            ->first();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->active()
            ->exists();
    }

    public function hasActiveSubscriptionForPrice(string $stripePriceId): bool
    {
        return $this->subscriptions()
            ->active()
            ->whereHas('price', function ($query) use ($stripePriceId) {
                $query->where('stripe_price_id', $stripePriceId);
            })
            ->exists();
    }

    public function isSubscribedTo(string $planSlug): bool
    {
        return $this->subscriptions()
            ->active()
            ->whereHas('plan', function ($query) use ($planSlug) {
                $query->where('slug', $planSlug);
            })
            ->exists();
    }

    public function canAccessFeature(string $featureSlug): bool
    {
        return app(FeatureGateService::class)->hasFeatureAccess($this, $featureSlug);
    }

    public function getCurrentUsage(string $actionType): int
    {
        return app(FeatureGateService::class)->getCurrentUsage($this, $actionType);
    }

    public function getUsageLimit(string $actionType): ?int
    {
        return app(FeatureGateService::class)->getUsageLimit($this, $actionType);
    }
}
