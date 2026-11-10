<?php

namespace App\Services\Stripe;

use Stripe\Subscription;
use Stripe\StripeClient;

class StripeSubscriptionService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient([
            'api_key' => config('stripe.secret'),
            'stripe_version' => config('stripe.api_version'),
        ]);
    }

    public function retrieve(string $subscriptionId): Subscription
    {
        return $this->stripe->subscriptions->retrieve($subscriptionId);
    }

    public function cancelAtPeriodEnd(string $subscriptionId): Subscription
    {
        return $this->stripe->subscriptions->update($subscriptionId, [
            'cancel_at_period_end' => true,
        ]);
    }

    public function cancelImmediately(string $subscriptionId): Subscription
    {
        return $this->stripe->subscriptions->cancel($subscriptionId);
    }
}
