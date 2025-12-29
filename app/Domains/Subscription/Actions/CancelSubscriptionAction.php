<?php

namespace App\Domains\Subscription\Actions;

use App\Domains\Subscription\Services\SubscriptionManager;
use App\Models\Subscription;

/**
 * Action for canceling a subscription
 * 
 * This action coordinates the cancellation process through
 * the SubscriptionManager service.
 */
class CancelSubscriptionAction
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager
    ) {}

    /**
     * Cancel subscription at period end
     */
    public function execute(Subscription $subscription): Subscription
    {
        return $this->subscriptionManager->cancelAtPeriodEnd($subscription);
    }

    /**
     * Cancel subscription immediately
     */
    public function executeImmediately(Subscription $subscription): Subscription
    {
        return $this->subscriptionManager->cancelImmediately($subscription);
    }
}