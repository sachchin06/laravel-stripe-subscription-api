<?php

namespace App\Domains\Webhooks\Jobs;

use App\Domains\Subscription\Services\SubscriptionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Handles customer.subscription.created and customer.subscription.updated by re-reading the subscription from Stripe.
 */
class HandleSubscriptionUpdated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private readonly array $subscriptionData
    ) {}

    public function handle(SubscriptionManager $subscriptionManager): void
    {
        $stripeSubscriptionId = $this->subscriptionData['id'];

        $subscription = $subscriptionManager->syncFromStripe($stripeSubscriptionId);

        if (! $subscription) {
            Log::info('Subscription not stored locally yet; skipped', [
                'stripe_subscription_id' => $stripeSubscriptionId,
            ]);

            return;
        }

        Log::info('Subscription synced from Stripe', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->stripe_status,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('HandleSubscriptionUpdated failed permanently', [
            'stripe_subscription_id' => $this->subscriptionData['id'] ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }
}
