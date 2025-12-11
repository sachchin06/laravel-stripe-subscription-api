<?php

namespace App\Actions\Subscription;

use App\Events\SubscriptionCanceled;
use App\Exceptions\SubscriptionException;
use App\Models\Subscription;
use App\Services\Stripe\StripeSubscriptionService;

class CancelSubscriptionAction
{
    public function __construct(
        private readonly StripeSubscriptionService $subscriptionService
    ) {}

    public function execute(Subscription $subscription): void
    {
        if (! in_array($subscription->stripe_status, ['active', 'trialing'])) {
            throw SubscriptionException::notActive();
        }

        $this->subscriptionService->cancelAtPeriodEnd($subscription->stripe_subscription_id);

        event(new SubscriptionCanceled($subscription));
    }
}
