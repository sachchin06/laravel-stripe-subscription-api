<?php

namespace App\Actions\Subscription;

use App\DataTransferObjects\SubscriptionData;
use App\Events\SubscriptionCreated;
use App\Models\Subscription;

class CreateSubscriptionAction
{
    public function execute(SubscriptionData $data): Subscription
    {
        $subscription = Subscription::create($data->toArray());

        event(new SubscriptionCreated($subscription));

        return $subscription;
    }
}
