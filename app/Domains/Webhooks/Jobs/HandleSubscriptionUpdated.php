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
 * Job for handling customer.subscription.updated webhook events
 * 
 * This job processes subscription updates from Stripe and syncs
 * the changes to the local database.
 */
class HandleSubscriptionUpdated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying
     */
    public int $backoff = 60;

    public function __construct(
        private readonly array $subscriptionData
    ) {}

    /**
     * Handle the webhook event
     */
    public function handle(SubscriptionManager $subscriptionManager): void
    {
        try {
            Log::info('Processing customer.subscription.updated webhook', [
                'stripe_subscription_id' => $this->subscriptionData['id'] ?? 'unknown',
            ]);

            // Update subscription from Stripe data
            $subscription = $subscriptionManager->updateFromStripeWebhook($this->subscriptionData);

            if (!$subscription) {
                Log::warning('Subscription not found for update', [
                    'stripe_subscription_id' => $this->subscriptionData['id'] ?? 'unknown',
                ]);
                return;
            }

            Log::info('Subscription updated from webhook', [
                'subscription_id' => $subscription->id,
                'status' => $subscription->stripe_status,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle customer.subscription.updated', [
                'error' => $e->getMessage(),
                'stripe_subscription_id' => $this->subscriptionData['id'] ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('HandleSubscriptionUpdated job failed permanently', [
            'stripe_subscription_id' => $this->subscriptionData['id'] ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }
}