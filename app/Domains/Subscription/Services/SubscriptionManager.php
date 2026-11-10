<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Shared\Services\StripeService;
use App\Domains\Subscription\DTOs\SubscriptionData;
use App\Enums\SubscriptionStatus;
use App\Events\SubscriptionCanceled;
use App\Events\SubscriptionCreated;
use App\Events\SubscriptionUpdated;
use App\Exceptions\SubscriptionException;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

    public function createFromCheckoutSession(array $sessionData): Subscription
    {
        $session = \Stripe\Checkout\Session::constructFrom($sessionData);

        if (! $session->subscription) {
            throw SubscriptionException::invalidCheckoutSession('No subscription found in checkout session');
        }

        $userId = (int) $session->client_reference_id;
        if (! $userId) {
            throw SubscriptionException::invalidCheckoutSession('Missing client_reference_id');
        }

        // Fast path for a repeated event: no need to call Stripe again.
        $existingSubscription = Subscription::where('stripe_subscription_id', $session->subscription)->first();
        if ($existingSubscription) {
            return $existingSubscription;
        }

        $stripeSubscription = $this->stripeService->retrieveSubscription($session->subscription);

        $priceId = $stripeSubscription->items->data[0]->price->id;
        $planPrice = PlanPrice::where('stripe_price_id', $priceId)->first();

        if (! $planPrice) {
            throw SubscriptionException::invalidCheckoutSession("Price {$priceId} not found in database");
        }

        $user = User::find($userId);
        if ($user && ! $user->stripe_customer_id) {
            $user->update(['stripe_customer_id' => $stripeSubscription->customer]);
        }

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

        // Two workers can handle the same checkout at the same time. createOrFirst relies on
        // the unique index on stripe_subscription_id: the second insert fails and the row
        // written by the first worker is returned instead of a duplicate being created.
        $subscription = Subscription::createOrFirst(
            ['stripe_subscription_id' => $stripeSubscription->id],
            $subscriptionData->toArray()
        );

        if ($subscription->wasRecentlyCreated) {
            event(new SubscriptionCreated($subscription));
            $this->warnIfUserHasOtherActiveSubscriptions($subscription);
        }

        return $subscription;
    }

    /**
     * Checkout expires older sessions, so this should not happen. If it does
     * (e.g. a session paid in the moment before it was expired), the customer
     * really was charged twice, so the subscription is stored and left for a
     * person to resolve rather than silently dropped.
     */
    private function warnIfUserHasOtherActiveSubscriptions(Subscription $subscription): void
    {
        $others = Subscription::active()
            ->where('user_id', $subscription->user_id)
            ->whereKeyNot($subscription->getKey())
            ->pluck('stripe_subscription_id');

        if ($others->isNotEmpty()) {
            Log::warning('User has more than one active subscription', [
                'user_id' => $subscription->user_id,
                'new_stripe_subscription_id' => $subscription->stripe_subscription_id,
                'other_stripe_subscription_ids' => $others->all(),
            ]);
        }
    }

    /**
     * Overwrite the local subscription with its current state in Stripe.
     *
     * Webhook payloads can arrive late or out of order, so they are only used to
     * learn which subscription changed. The state written here always comes from
     * a fresh API call, which means an old event can never overwrite newer data.
     * Returns null if the subscription is not stored locally yet; the
     * checkout.session.completed handler creates it.
     */
    public function syncFromStripe(string $stripeSubscriptionId): ?Subscription
    {
        if (! Subscription::where('stripe_subscription_id', $stripeSubscriptionId)->exists()) {
            return null;
        }

        // Serialise syncs of the same subscription so two jobs cannot interleave
        // their read from Stripe and their write to the database.
        return Cache::lock("stripe-subscription-sync:{$stripeSubscriptionId}", 30)
            ->block(10, function () use ($stripeSubscriptionId) {
                $stripeSubscription = $this->stripeService->retrieveSubscription($stripeSubscriptionId);
                $subscription = Subscription::where('stripe_subscription_id', $stripeSubscriptionId)->firstOrFail();

                $attributes = [
                    'stripe_status' => $stripeSubscription->status,
                    'trial_ends_at' => $stripeSubscription->trial_end ?
                        Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                    'ends_at' => $this->endsAt($stripeSubscription),
                ];

                // Plan changes made in the Stripe Billing Portal reach us this way.
                $priceId = $stripeSubscription->items->data[0]->price->id;
                $planPrice = PlanPrice::where('stripe_price_id', $priceId)->first();

                if ($planPrice) {
                    $attributes['plan_id'] = $planPrice->plan_id;
                    $attributes['plan_price_id'] = $planPrice->id;
                } else {
                    Log::warning('Stripe price not found locally; plan left unchanged', [
                        'stripe_subscription_id' => $stripeSubscriptionId,
                        'stripe_price_id' => $priceId,
                    ]);
                }

                $subscription->update($attributes);

                event(new SubscriptionUpdated($subscription));

                return $subscription;
            });
    }

    /**
     * When access ends: the end date for a cancelled subscription, or the
     * scheduled cancellation date for one set to cancel at period end.
     */
    private function endsAt(\Stripe\Subscription $stripeSubscription): ?Carbon
    {
        if ($stripeSubscription->status === SubscriptionStatus::CANCELED->value) {
            $timestamp = $stripeSubscription->ended_at ?? $stripeSubscription->canceled_at;
        } elseif ($stripeSubscription->cancel_at) {
            $timestamp = $stripeSubscription->cancel_at;
        } elseif ($stripeSubscription->cancel_at_period_end) {
            $timestamp = $stripeSubscription->items->data[0]->current_period_end;
        } else {
            $timestamp = null;
        }

        return $timestamp ? Carbon::createFromTimestamp($timestamp) : null;
    }

    public function cancelAtPeriodEnd(Subscription $subscription): Subscription
    {
        if (! $subscription->isActive()) {
            throw SubscriptionException::notActive();
        }

        // Stripe first, then a single local write. No transaction is needed, and a
        // failure in Stripe leaves the local record unchanged.
        $stripeSubscription = $this->stripeService->updateSubscription($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => true,
        ]);

        // Use the end date Stripe reports, which is right for monthly and yearly prices alike.
        $subscription->update([
            'ends_at' => $this->endsAt($stripeSubscription),
        ]);

        event(new SubscriptionCanceled($subscription));

        return $subscription;
    }

    public function cancelImmediately(Subscription $subscription): Subscription
    {
        if (! $subscription->isActive()) {
            throw SubscriptionException::notActive();
        }

        $this->stripeService->cancelSubscription($subscription->stripe_subscription_id);

        $subscription->update([
            'stripe_status' => SubscriptionStatus::CANCELED,
            'ends_at' => now(),
        ]);

        event(new SubscriptionCanceled($subscription));

        return $subscription;
    }

    public function hasActiveSubscription(User $user): bool
    {
        return $user->subscriptions()
            ->active()
            ->exists();
    }

    public function getActiveSubscription(User $user): ?Subscription
    {
        return $user->subscriptions()
            ->active()
            ->with(['plan', 'price'])
            ->first();
    }
}
