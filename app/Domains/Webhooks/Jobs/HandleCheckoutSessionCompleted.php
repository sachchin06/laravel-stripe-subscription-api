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
 * Job for handling checkout.session.completed webhook events
 * 
 * This job processes successful checkout sessions and creates
 * the corresponding subscription in the local database.
 */
class HandleCheckoutSessionCompleted implements ShouldQueue
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

    /**
     * Delete the job if its models no longer exist
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        private readonly array $sessionData
    ) {}

    /**
     * Handle the webhook event
     */
    public function handle(SubscriptionManager $subscriptionManager): void
    {
        try {
            Log::info('Processing checkout.session.completed webhook', [
                'session_id' => $this->sessionData['id'] ?? 'unknown',
            ]);

            // Validate session data
            if (!isset($this->sessionData['subscription'])) {
                Log::warning('Checkout session has no subscription ID', [
                    'session_id' => $this->sessionData['id'] ?? 'unknown',
                ]);
                return;
            }

            if (!isset($this->sessionData['client_reference_id'])) {
                Log::error('Checkout session missing client_reference_id', [
                    'session_id' => $this->sessionData['id'] ?? 'unknown',
                ]);
                return;
            }

            // Create subscription from checkout session
            $subscription = $subscriptionManager->createFromCheckoutSession($this->sessionData);

            Log::info('Subscription created from checkout session', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'stripe_subscription_id' => $subscription->stripe_subscription_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle checkout.session.completed', [
                'error' => $e->getMessage(),
                'session_id' => $this->sessionData['id'] ?? 'unknown',
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
        Log::error('HandleCheckoutSessionCompleted job failed permanently', [
            'session_id' => $this->sessionData['id'] ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }
}