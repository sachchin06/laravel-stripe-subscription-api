<?php

namespace App\Domains\Webhooks\Jobs;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for handling invoice.paid webhook events
 * 
 * This job processes successful invoice payments and can be used
 * to trigger notifications or update subscription status.
 */
class HandleInvoicePaid implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly array $invoiceData
    ) {}

    public function handle(): void
    {
        try {
            Log::info('Processing invoice.paid webhook', [
                'invoice_id' => $this->invoiceData['id'] ?? 'unknown',
                'subscription_id' => $this->invoiceData['subscription'] ?? 'unknown',
            ]);

            $subscriptionId = $this->invoiceData['subscription'] ?? null;
            
            if (!$subscriptionId) {
                Log::info('Invoice has no subscription (one-time payment)', [
                    'invoice_id' => $this->invoiceData['id'] ?? 'unknown',
                ]);
                return;
            }

            // Find and update subscription if needed
            $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();

            if ($subscription) {
                // Ensure subscription is marked as active after successful payment
                if ($subscription->stripe_status !== 'active') {
                    $subscription->update(['stripe_status' => 'active']);
                    Log::info('Subscription status updated to active after payment', [
                        'subscription_id' => $subscription->id,
                    ]);
                }
            }

            Log::info('Invoice paid processed successfully', [
                'invoice_id' => $this->invoiceData['id'] ?? 'unknown',
                'amount_paid' => $this->invoiceData['amount_paid'] ?? 0,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle invoice.paid', [
                'error' => $e->getMessage(),
                'invoice_id' => $this->invoiceData['id'] ?? 'unknown',
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('HandleInvoicePaid job failed permanently', [
            'invoice_id' => $this->invoiceData['id'] ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }
}