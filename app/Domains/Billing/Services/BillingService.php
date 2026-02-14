<?php

namespace App\Domains\Billing\Services;

use App\Domains\Shared\Services\StripeService;
use App\Domains\Billing\DTOs\CheckoutSessionData;
use App\Models\User;
use App\Models\PlanPrice;
use App\Exceptions\SubscriptionException;

/**
 * Service for handling billing operations
 * 
 * This service manages checkout sessions, payment processing,
 * and billing-related operations with Stripe.
 */
class BillingService
{
    public function __construct(
        private readonly StripeService $stripeService
    ) {}

    /**
     * Create a Stripe checkout session for subscription
     */
    public function createCheckoutSession(CheckoutSessionData $data): \Stripe\Checkout\Session
    {
        $params = [
            'mode' => 'subscription',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $data->stripePriceId,
                'quantity' => 1,
            ]],
            'client_reference_id' => (string) $data->userId,
            'success_url' => $data->successUrl,
            'cancel_url' => $data->cancelUrl,
            'allow_promotion_codes' => true,
            'billing_address_collection' => 'required',
            'metadata' => array_merge([
                'user_id' => $data->userId,
                'price_id' => $data->stripePriceId,
            ], $data->metadata),
            'subscription_data' => [
                'metadata' => [
                    'user_id' => $data->userId,
                ],
            ],
        ];

        // Add customer if exists
        if ($data->stripeCustomerId) {
            $params['customer'] = $data->stripeCustomerId;
        } else {
            $params['customer_email'] = $data->customerEmail;
        }

        // Add trial period if specified
        if ($data->trialPeriodDays) {
            $params['subscription_data']['trial_period_days'] = $data->trialPeriodDays;
        }

        return $this->stripeService->createCheckoutSession($params);
    }

    /**
     * Create or retrieve Stripe customer for user
     */
    public function getOrCreateCustomer(User $user): \Stripe\Customer
    {
        // Check if user already has a Stripe customer ID
        if ($user->stripe_customer_id) {
            try {
                return $this->stripeService->retrieveCustomer($user->stripe_customer_id);
            } catch (\Exception $e) {
                // Customer not found in Stripe, create new one
            }
        }

        // Create new customer
        $customer = $this->stripeService->createCustomer([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id
            ]
        ]);

        // Update user with Stripe customer ID
        $user->update(['stripe_customer_id' => $customer->id]);

        return $customer;
    }

    /**
     * Validate that user can subscribe to the given plan price
     */
    public function validateSubscriptionEligibility(User $user, PlanPrice $planPrice): void
    {
        // Check if user already has active subscription for this plan
        $hasActiveSubscription = $user->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->where('plan_price_id', $planPrice->id)
            ->exists();

        if ($hasActiveSubscription) {
            throw SubscriptionException::alreadySubscribed();
        }
    }

    /**
     * Generate success and cancel URLs for checkout
     */
    public function generateCheckoutUrls(): array
    {
        $baseUrl = config('app.frontend_url', config('app.url'));
        
        return [
            'success_url' => $baseUrl . '/subscription/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $baseUrl . '/subscription/cancel'
        ];
    }
}