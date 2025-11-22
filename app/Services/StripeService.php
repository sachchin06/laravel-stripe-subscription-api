<?php

namespace App\Services;

use App\Models\Subscription;
use Stripe\StripeClient;


class StripeService
{

    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config(('services.stripe.secret')));
    }

    public function createCheckoutSession($userId, $priceId, $successUrl, $cancelUrl)
    {
        return $this->stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'client_reference_id' => $userId,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
    }

    public function cancelSubscription(Subscription $subscription)
    {
        $subscriptionId = $subscription->stripe_subscription_id;

        return $this->stripe->subscriptions->update($subscriptionId, [
            'cancel_at_period_end' => true,
        ]);
    }
}
