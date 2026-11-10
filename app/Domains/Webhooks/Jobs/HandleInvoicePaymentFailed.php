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
 * Handles invoice.payment_failed. The invoice only tells us which subscription to refresh;
 * its status is re-read from Stripe rather than inferred from the event.
 */
class HandleInvoicePaymentFailed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private readonly array $invoiceData
    ) {}

    public function handle(SubscriptionManager $subscriptionManager): void
    {
        // Since the 2025 API versions the subscription ID lives under the invoice's parent.
        $stripeSubscriptionId = data_get($this->invoiceData, 'parent.subscription_details.subscription');

        if (! $stripeSubscriptionId) {
            Log::info('Invoice is not linked to a subscription; skipped', [
                'invoice_id' => $this->invoiceData['id'] ?? 'unknown',
            ]);

            return;
        }

        $subscription = $subscriptionManager->syncFromStripe($stripeSubscriptionId);

        Log::warning('Invoice payment failed; subscription synced', [
            'invoice_id' => $this->invoiceData['id'] ?? 'unknown',
            'subscription_id' => $subscription?->id,
            'status' => $subscription?->stripe_status,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('HandleInvoicePaymentFailed failed permanently', [
            'invoice_id' => $this->invoiceData['id'] ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }
}
