<?php

namespace App\Actions\Subscription;

use App\Exceptions\SubscriptionException;
use App\Models\PlanPrice;
use App\Models\User;
use App\Services\Stripe\StripeCheckoutService;

class CreateCheckoutSessionAction
{
    public function __construct(
        private readonly StripeCheckoutService $checkoutService
    ) {}

    public function execute(User $user, string $stripePriceId): string
    {
        $price = PlanPrice::where('stripe_price_id', $stripePriceId)->firstOrFail();

        if ($user->hasActiveSubscriptionForPrice($stripePriceId)) {
            throw SubscriptionException::alreadySubscribed();
        }

        $session = $this->checkoutService->createSession(
            userId: $user->id,
            priceId: $price->stripe_price_id,
            successUrl: config('app.url') . '/api/subscription/success?session_id={CHECKOUT_SESSION_ID}',
            cancelUrl: config('app.url') . '/api/subscription/cancel'
        );

        return $session->url;
    }
}
