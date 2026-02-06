<?php

namespace App\Domains\Webhooks\Services;

use App\Domains\Shared\Services\StripeService;
use App\Domains\Webhooks\Jobs\HandleCheckoutSessionCompleted;
use App\Domains\Webhooks\Jobs\HandleSubscriptionUpdated;
use App\Domains\Webhooks\Jobs\HandleSubscriptionDeleted;
use App\Domains\Webhooks\Jobs\HandleInvoicePaid;
use App\Domains\Webhooks\Jobs\HandleInvoicePaymentFailed;
use App\Exceptions\StripeWebhookException;
use Illuminate\Support\Facades\Log;

/**
 * Service for processing Stripe webhooks
 * 
 * This service handles webhook validation and dispatches
 * appropriate jobs for async processing.
 */
class WebhookService
{
    public function __construct(
        private readonly StripeService $stripeService
    ) {}

    /**
     * Process incoming webhook payload
     */
    public function processWebhook(string $payload, ?string $signature): void
    {
        if (!$signature) {
            throw StripeWebhookException::invalidSignature('Missing Stripe-Signature header');
        }

        try {
            // Construct and validate the webhook event
            $event = $this->stripeService->constructWebhookEvent($payload, $signature);
            
            Log::info('Stripe webhook received', [
                'type' => $event->type,
                'id' => $event->id,
            ]);

            // Dispatch appropriate job based on event type
            $this->dispatchWebhookJob($event);

            Log::info('Webhook job dispatched successfully', [
                'event_type' => $event->type,
                'event_id' => $event->id,
            ]);

        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Invalid webhook signature', [
                'error' => $e->getMessage(),
            ]);
            throw StripeWebhookException::invalidSignature($e->getMessage());
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw StripeWebhookException::processingFailed($e->getMessage());
        }
    }

    /**
     * Dispatch the appropriate job for the webhook event
     */
    private function dispatchWebhookJob(\Stripe\Event $event): void
    {
        $eventData = $event->data->object->toArray();

        match ($event->type) {
            // Checkout events
            'checkout.session.completed' => HandleCheckoutSessionCompleted::dispatch($eventData),
            
            // Subscription events
            'customer.subscription.created' => HandleSubscriptionUpdated::dispatch($eventData),
            'customer.subscription.updated' => HandleSubscriptionUpdated::dispatch($eventData),
            'customer.subscription.deleted' => HandleSubscriptionDeleted::dispatch($eventData),
            
            // Invoice events
            'invoice.paid' => HandleInvoicePaid::dispatch($eventData),
            'invoice.payment_failed' => HandleInvoicePaymentFailed::dispatch($eventData),
            
            // Unhandled events
            default => Log::info('Unhandled Stripe event type', ['type' => $event->type]),
        };
    }

    /**
     * Get supported webhook event types
     */
    public function getSupportedEventTypes(): array
    {
        return [
            'checkout.session.completed',
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'invoice.paid',
            'invoice.payment_failed',
        ];
    }
}