<?php

namespace App\Domains\Subscription\Actions;

use App\Domains\Subscription\DTOs\SubscriptionData;
use App\Domains\Subscription\Events\SubscriptionCreated;
use App\Models\Subscription;

/**
 * Action for creating a new subscription
 * 
 * This action handles the creation of a subscription record
 * and fires the appropriate domain event.
 */
class CreateSubscriptionAction
{
    /**
     * Execute the subscription creation
     */
    public function execute(SubscriptionData $data): Subscription
    {
        $subscription = Subscription::create($data->toArray());

        // Fire domain event for other parts of the system to react
        event(new SubscriptionCreated($subscription));

        return $subscription;
    }
}