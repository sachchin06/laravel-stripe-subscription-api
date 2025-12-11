<?php

namespace App\Actions\Subscription;

use App\Events\SubscriptionUpdated;
use App\Models\Subscription;
use Carbon\Carbon;

class UpdateSubscriptionStatusAction
{
    public function execute(
        Subscription $subscription,
        string $status,
        ?int $cancelAt = null
    ): Subscription {
        $subscription->stripe_status = $status;

        if ($cancelAt) {
            $subscription->ends_at = Carbon::createFromTimestamp($cancelAt);
        }

        $subscription->save();

        event(new SubscriptionUpdated($subscription));

        return $subscription;
    }
}
