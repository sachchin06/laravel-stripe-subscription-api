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
 * Handles checkout.session.completed by creating the local subscription.
 */
class HandleCheckoutSessionCompleted implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private readonly array $sessionData
    ) {}

    public function handle(SubscriptionManager $subscriptionManager): void
    {
        $sessionId = $this->sessionData['id'] ?? 'unknown';

        // One-off payments have no subscription; nothing to store.
        if (! isset($this->sessionData['subscription'])) {
            Log::info('Checkout session has no subscription; skipped', ['session_id' => $sessionId]);

            return;
        }

        // Every checkout this API creates sets client_reference_id, so a missing one
        // means the session was created elsewhere and cannot be linked to a user.
        if (! isset($this->sessionData['client_reference_id'])) {
            Log::error('Checkout session missing client_reference_id; skipped', ['session_id' => $sessionId]);

            return;
        }

        $subscription = $subscriptionManager->createFromCheckoutSession($this->sessionData);

        Log::info('Subscription stored from checkout session', [
            'session_id' => $sessionId,
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('HandleCheckoutSessionCompleted failed permanently', [
            'session_id' => $this->sessionData['id'] ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }
}
