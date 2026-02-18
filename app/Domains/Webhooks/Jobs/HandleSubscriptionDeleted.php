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
 * Job for handling customer.subscription.deleted webhook events
 * 
 * This job processes subscription deletions from Stripe and updates
 * the local subscription status accordingly.
 */
class HandleSubscriptionDeleted implements ShouldQueue
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
            Log::info('Processing customer.subscription.deleted webhook', [
                'stripe_subscription_id' => $this->subscriptionData['id'] ?? 'unknown',
            ]);

            // Mark subscription as canceled
            $subscriptionData = $this->subscriptionData;
            $subscriptionData['status'] = 'canceled';
            
            $subscription = $subscriptionManager->updateFromStripeWebhook($subscriptionData);

            if (!$subscription) {
                Log::warning('Subscription not found for deletion', [
                    'stripe_subscription_id' => $this->subscriptionData['id'] ?? 'unknown',
                ]);
                return;
            }

            Log::info('Subscription marked as deleted from webhook', [
                'subscription_id' => $subscription->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle customer.subscription.deleted', [
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
        Log::error('HandleSubscriptionDeleted job failed permanently', [
            'stripe_subscription_id' => $this->subscriptionData['id'] ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }
}