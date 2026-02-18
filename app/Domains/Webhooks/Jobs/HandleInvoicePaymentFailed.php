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
 * Job for handling invoice.payment_failed webhook events
 * 
 * This job processes failed invoice payments and can be used
 * to notify users or update subscription status.
 */
class HandleInvoicePaymentFailed implements ShouldQueue
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
            Log::warning('Processing invoice.payment_failed webhook', [
                'invoice_id' => $this->invoiceData['id'] ?? 'unknown',
                'subscription_id' => $this->invoiceData['subscription'] ?? 'unknown',
            ]);

            $subscriptionId = $this->invoiceData['subscription'] ?? null;
            
            if (!$subscriptionId) {
                return;
            }

            $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();

            if ($subscription) {
                // Update status to past_due if payment failed
                $subscription->update(['stripe_status' => 'past_due']);
                
                Log::warning('Subscription marked as past_due due to payment failure', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                ]);

                // TODO: Send notification to user about failed payment
                // You can dispatch a notification job here
            }

        } catch (\Exception $e) {
            Log::error('Failed to handle invoice.payment_failed', [
                'error' => $e->getMessage(),
                'invoice_id' => $this->invoiceData['id'] ?? 'unknown',
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('HandleInvoicePaymentFailed job failed permanently', [
            'invoice_id' => $this->invoiceData['id'] ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }
}