<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Shared\Services\StripeService;
use App\Models\Subscription;
use App\Models\User;
use App\Models\PlanPrice;
use App\Domains\Subscription\DTOs\SubscriptionData;
use App\Events\SubscriptionCreated;
use App\Events\SubscriptionUpdated;
use App\Events\SubscriptionCanceled;
use App\Exceptions\SubscriptionException;
use Illuminate\Support\Facades\DB;

/**
 * High-level subscription management service
 * 
 * This service orchestrates subscription operations and ensures
 * consistency between local database and Stripe.
 */
class SubscriptionManager
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly PlanService $planService
    ) {}

    /**
     * Create a new subscription from Stripe checkout session
     */
    public function createFromCheckoutSession(array $sessionData): Subscription
    {
        return DB::transaction(function () use ($sessionData) {
            $session = \Stripe\Checkout\Session::constructFrom($sessionData);
            
            // Validate session has subscription
            if (!$session->subscription) {
                throw SubscriptionException::invalidCheckoutSession('No subscription found in checkout session');
            }

            // Check if subscription already exists (idempotency)
            $existingSubscription = Subscription::where('stripe_subscription_id', $session->subscription)->first();
            if ($existingSubscription) {
                return $existingSubscription;
            }

            // Get subscription details from Stripe
            $stripeSubscription = $this->stripeService->retrieveSubscription($session->subscription);
            
            // Find the plan price
            $priceId = $stripeSubscription->items->data[0]->price->id;
            $planPrice = PlanPrice::where('stripe_price_id', $priceId)->first();
            
            if (!$planPrice) {
                throw SubscriptionException::invalidCheckoutSession("Price {$priceId} not found in database");
            }

            // Get user ID from client_reference_id
            $userId = (int) $session->client_reference_id;
            if (!$userId) {
                throw SubscriptionException::invalidCheckoutSession('Missing client_reference_id');
            }

            // Update user's stripe_customer_id if not set
            $user = User::find($userId);
            if ($user && !$user->stripe_customer_id) {
                $user->update(['stripe_customer_id' => $stripeSubscription->customer]);
            }
            
            // Create subscription data
            $subscriptionData = new SubscriptionData(
                userId: $userId,
                planId: $planPrice->plan_id,
                planPriceId: $planPrice->id,
                stripeSubscriptionId: $stripeSubscription->id,
                stripeCustomerId: $stripeSubscription->customer,
                stripeStatus: $stripeSubscription->status,
                trialEndsAt: $stripeSubscription->trial_end ? 
                    \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                endsAt: null
            );

            // Create local subscription
            $subscription = Subscription::create($subscriptionData->toArray());
            
            // Fire event
            event(new SubscriptionCreated($subscription));
            
            return $subscription;
        });
    }

    /**
     * Update subscription status from Stripe webhook
     */
    public function updateFromStripeWebhook(array $stripeSubscriptionData): ?Subscription
    {
        $stripeSubscription = \Stripe\Subscription::constructFrom($stripeSubscriptionData);
        
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();
        
        if (!$subscription) {
            return null;
        }

        return DB::transaction(function () use ($subscription, $stripeSubscription) {
            $subscription->update([
                'stripe_status' => $stripeSubscription->status,
                'trial_ends_at' => $stripeSubscription->trial_end ? 
                    \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                'ends_at' => $stripeSubscription->cancel_at ? 
                    \Carbon\Carbon::createFromTimestamp($stripeSubscription->cancel_at) : null,
            ]);

            event(new SubscriptionUpdated($subscription));
            
            return $subscription;
        });
    }

    /**
     * Cancel subscription at period end
     */
    public function cancelAtPeriodEnd(Subscription $subscription): Subscription
    {
        if (!$subscription->isActive()) {
            throw SubscriptionException::notActive();
        }

        return DB::transaction(function () use ($subscription) {
            // Cancel in Stripe
            $this->stripeService->updateSubscription($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => true
            ]);

            // Update local record
            $subscription->update([
                'ends_at' => now()->addMonth() // This will be updated by webhook
            ]);

            event(new SubscriptionCanceled($subscription));
            
            return $subscription;
        });
    }

    /**
     * Cancel subscription immediately
     */
    public function cancelImmediately(Subscription $subscription): Subscription
    {
        if (!$subscription->isActive()) {
            throw SubscriptionException::notActive();
        }

        return DB::transaction(function () use ($subscription) {
            // Cancel in Stripe
            $this->stripeService->cancelSubscription($subscription->stripe_subscription_id);

            // Update local record
            $subscription->update([
                'stripe_status' => 'canceled',
                'ends_at' => now()
            ]);

            event(new SubscriptionCanceled($subscription));
            
            return $subscription;
        });
    }

    /**
     * Check if user has active subscription
     */
    public function hasActiveSubscription(User $user): bool
    {
        return $user->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->exists();
    }

    /**
     * Get user's active subscription
     */
    public function getActiveSubscription(User $user): ?Subscription
    {
        return $user->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->with(['plan', 'price'])
            ->first();
    }

    /**
     * Check if user can subscribe to a plan
     */
    public function canSubscribeTo(User $user, PlanPrice $planPrice): bool
    {
        // Check if user already has active subscription for this plan
        return !$user->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->where('plan_price_id', $planPrice->id)
            ->exists();
    }
}