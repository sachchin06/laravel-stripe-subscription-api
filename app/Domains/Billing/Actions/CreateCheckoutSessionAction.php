<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Services\BillingService;
use App\Domains\Billing\DTOs\CheckoutSessionData;
use App\Domains\Subscription\Services\PlanService;
use App\Models\User;
use App\Models\PlanPrice;
use App\Exceptions\SubscriptionException;

/**
 * Action for creating Stripe checkout sessions
 * 
 * This action orchestrates the checkout session creation process,
 * including validation and URL generation.
 */
class CreateCheckoutSessionAction
{
    public function __construct(
        private readonly BillingService $billingService,
        private readonly PlanService $planService
    ) {}

    /**
     * Create a checkout session for the given user and plan price
     */
    public function execute(User $user, string $stripePriceId): string
    {
        // Find the plan price
        $planPrice = $this->planService->findPriceByStripeId($stripePriceId);
        
        if (!$planPrice) {
            throw SubscriptionException::invalidPlanPrice();
        }

        // Validate subscription eligibility
        $this->billingService->validateSubscriptionEligibility($user, $planPrice);

        // Get or create Stripe customer
        $customer = $this->billingService->getOrCreateCustomer($user);

        // Generate checkout URLs
        $urls = $this->billingService->generateCheckoutUrls();

        // Create checkout session data
        $checkoutData = new CheckoutSessionData(
            userId: $user->id,
            stripePriceId: $stripePriceId,
            customerEmail: $user->email,
            successUrl: $urls['success_url'],
            cancelUrl: $urls['cancel_url'],
            stripeCustomerId: $customer->id
        );

        // Create the checkout session
        $session = $this->billingService->createCheckoutSession($checkoutData);

        return $session->url;
    }
}