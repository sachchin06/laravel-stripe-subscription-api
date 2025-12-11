<?php

namespace App\Jobs\Webhooks;

use App\Actions\Subscription\UpdateSubscriptionStatusAction;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stripe\Subscription as StripeSubscription;

class HandleSubscriptionDeleted implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly array $subscriptionData
    ) {}

    public function handle(
        UpdateSubscriptionStatusAction $updateStatus,
        \App\Repositories\SubscriptionRepository $repository
    ): void {
        try {
            $stripeSubscription = StripeSubscription::constructFrom($this->subscriptionData);

            $subscription = $repository->findByStripeCustomerAndSubscription(
                $stripeSubscription->customer,
                $stripeSubscription->id
            );

            if (! $subscription) {
                Log::warning('Subscription not found for deletion', [
                    'stripe_subscription_id' => $stripeSubscription->id,
                ]);
                return;
            }

            $updateStatus->execute(
                subscription: $subscription,
                status: 'canceled',
                cancelAt: null
            );

            Log::info('Subscription deleted successfully', [
                'subscription_id' => $subscription->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to handle customer.subscription.deleted', [
                'error' => $e->getMessage(),
                'stripe_subscription_id' => $this->subscriptionData['id'] ?? 'unknown',
            ]);

            throw $e;
        }
    }
}
