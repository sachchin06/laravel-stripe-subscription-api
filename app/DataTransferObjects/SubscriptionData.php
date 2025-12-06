<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;

readonly class SubscriptionData
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
}
