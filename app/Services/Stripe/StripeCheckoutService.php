<?php

namespace App\Services\Stripe;

use Stripe\Checkout\Session;
use Stripe\StripeClient;

class StripeCheckoutService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('stripe.secret'));
    }

    public function createSession(
        int $userId,
        string $priceId,
        string $successUrl,
        string $cancelUrl
    ): Session {
        return $this->stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'client_reference_id' => (string) $userId,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'allow_promotion_codes' => true,
            'billing_address_collection' => 'required',
        ]);
    }
}
