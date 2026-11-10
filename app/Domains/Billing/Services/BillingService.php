<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\DTOs\CheckoutSessionData;
use App\Domains\Shared\Services\StripeService;
use App\Exceptions\SubscriptionException;
use App\Models\User;

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

    public function getOrCreateCustomer(User $user): \Stripe\Customer
    {
        $previousCustomerId = $user->stripe_customer_id;

        if ($previousCustomerId) {
            try {
                $customer = $this->stripeService->retrieveCustomer($previousCustomerId);

                if (! $customer->isDeleted()) {
                    return $customer;
                }
            } catch (\Stripe\Exception\InvalidRequestException) {
                // The stored customer no longer exists in Stripe; create a new one below.
            }
        }

        // The key is the same for concurrent checkout requests from this user, so
        // Stripe returns one customer to all of them instead of creating several.
        // Including the previous customer ID allows a replacement to be created.
        $idempotencyKey = sprintf(
            'customer-%d-%d-%s',
            $user->id,
            $user->created_at->getTimestamp(),
            $previousCustomerId ?? 'new'
        );

        $customer = $this->stripeService->createCustomer([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ], $idempotencyKey);

        $user->update(['stripe_customer_id' => $customer->id]);

        return $customer;
    }

    /**
     * A user may hold one active or trialing subscription at a time. Switching
     * plans goes through change-plan, which updates the existing subscription.
     */
    public function ensureNoActiveSubscription(User $user): void
    {
        if ($user->hasActiveSubscription()) {
            throw SubscriptionException::alreadySubscribed();
        }
    }

    /**
     * Expire every unpaid Checkout session the customer still has open.
     */
    public function expireOpenCheckoutSessions(string $customerId): void
    {
        $sessions = $this->stripeService->listOpenCheckoutSessions($customerId);

        foreach ($sessions->autoPagingIterator() as $session) {
            try {
                $this->stripeService->expireCheckoutSession($session->id);
            } catch (\Stripe\Exception\InvalidRequestException) {
                // It was completed or expired after we listed it; nothing to do.
            }
        }
    }

    public function generateCheckoutUrls(): array
    {
        $baseUrl = config('app.frontend_url');

        return [
            'success_url' => $baseUrl.'/subscription/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $baseUrl.'/subscription/cancel',
        ];
    }
}
