<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\DTOs\CheckoutSessionData;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Subscription\Services\PlanService;
use App\Exceptions\SubscriptionException;
use App\Models\User;

/**
 * Validates that the user may subscribe, then creates a Stripe Checkout
 * session and returns its URL.
 */
class CreateCheckoutSessionAction
{
    public function __construct(
        private readonly BillingService $billingService,
        private readonly PlanService $planService
    ) {}

    public function execute(User $user, string $stripePriceId): string
    {
        $planPrice = $this->planService->findPriceByStripeId($stripePriceId);

        if (!$planPrice) {
            throw SubscriptionException::invalidPlanPrice();
        }

        $this->billingService->ensureNoActiveSubscription($user);

        $customer = $this->billingService->getOrCreateCustomer($user);

        // Only the newest checkout can be paid, so a user with two tabs open
        // cannot end up paying for two subscriptions.
        $this->billingService->expireOpenCheckoutSessions($customer->id);

        $urls = $this->billingService->generateCheckoutUrls();

        $session = $this->billingService->createCheckoutSession(new CheckoutSessionData(
            userId: $user->id,
            stripePriceId: $stripePriceId,
            customerEmail: $user->email,
            successUrl: $urls['success_url'],
            cancelUrl: $urls['cancel_url'],
            stripeCustomerId: $customer->id
        ));

        return $session->url;
    }
}
