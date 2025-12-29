<?php

namespace App\Domains\Subscription\DTOs;

use App\Domains\Shared\DTOs\BaseDTO;
use Carbon\Carbon;

/**
 * Data transfer object for subscription creation and updates
 */
readonly class SubscriptionData extends BaseDTO
{
    public function __construct(
        public int $userId,
        public int $planId,
        public int $planPriceId,
        public string $stripeSubscriptionId,
        public string $stripeCustomerId,
        public string $stripeStatus,
        public ?Carbon $trialEndsAt = null,
        public ?Carbon $endsAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'plan_id' => $this->planId,
            'plan_price_id' => $this->planPriceId,
            'stripe_subscription_id' => $this->stripeSubscriptionId,
            'stripe_customer_id' => $this->stripeCustomerId,
            'stripe_status' => $this->stripeStatus,
            'trial_ends_at' => $this->trialEndsAt,
            'ends_at' => $this->endsAt,
        ];
    }

    /**
     * Create from Stripe subscription object
     */
    public static function fromStripeSubscription(
        \Stripe\Subscription $subscription,
        int $userId,
        int $planId,
        int $planPriceId
    ): self {
        return new self(
            userId: $userId,
            planId: $planId,
            planPriceId: $planPriceId,
            stripeSubscriptionId: $subscription->id,
            stripeCustomerId: $subscription->customer,
            stripeStatus: $subscription->status,
            trialEndsAt: $subscription->trial_end ? 
                Carbon::createFromTimestamp($subscription->trial_end) : null,
            endsAt: $subscription->cancel_at ? 
                Carbon::createFromTimestamp($subscription->cancel_at) : null
        );
    }
}