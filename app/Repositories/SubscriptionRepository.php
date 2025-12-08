<?php

namespace App\Repositories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionRepository
{
    public function findByStripeSubscriptionId(string $stripeSubscriptionId): ?Subscription
    {
        return Subscription::where('stripe_subscription_id', $stripeSubscriptionId)->first();
    }

    public function findByStripeCustomerAndSubscription(
        string $stripeCustomerId,
        string $stripeSubscriptionId
    ): ?Subscription {
        return Subscription::where('stripe_customer_id', $stripeCustomerId)
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->first();
    }

    public function getActiveSubscriptionsForUser(User $user): Collection
    {
        return $user->subscriptions()
            ->active()
            ->with(['plan', 'price'])
            ->get();
    }

    public function getUserActiveSubscription(User $user): ?Subscription
    {
        return $user->subscription()
            ->active()
            ->with(['plan', 'price'])
            ->first();
    }

    public function getExpiredSubscriptions(): Collection
    {
        return Subscription::where('stripe_status', 'canceled')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->get();
    }
}
