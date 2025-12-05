<?php

namespace App\Models;

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

    public function hasActiveSubscription(): bool
    {
        return $this->subscription()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->exists();
    }

    public function hasActiveSubscriptionForPrice(string $stripePriceId): bool
    {
        return $this->subscription()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->whereHas('price', function ($query) use ($stripePriceId) {
                $query->where('stripe_price_id', $stripePriceId);
            })
            ->exists();
    }

    public function isSubscribedTo(string $planSlug): bool
    {
        return $this->subscription()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->whereHas('plan', function ($query) use ($planSlug) {
                $query->where('slug', $planSlug);
            })
            ->exists();
    }
}
