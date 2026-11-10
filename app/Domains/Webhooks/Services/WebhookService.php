<?php

namespace App\Domains\Webhooks\Services;

use App\Domains\Shared\Services\StripeService;
use App\Domains\Webhooks\Jobs\HandleCheckoutSessionCompleted;
use App\Domains\Webhooks\Jobs\HandleSubscriptionUpdated;
use App\Domains\Webhooks\Jobs\HandleSubscriptionDeleted;
use App\Domains\Webhooks\Jobs\HandleInvoicePaid;
use App\Domains\Webhooks\Jobs\HandleInvoicePaymentFailed;
use App\Exceptions\StripeWebhookException;
use App\Models\StripeEvent;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

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
     * Verify the request and queue a job for the event.
     *
     * A bad signature or payload throws StripeWebhookException (400, so Stripe
     * does not retry). Any other failure, such as the database being down, is
     * left to the global handler, which returns a 5xx so that Stripe retries.
     */
    public function processWebhook(string $payload, ?string $signature): void
    {
        if (!$signature) {
            throw StripeWebhookException::invalidSignature();
        }

        try {
            $event = $this->stripeService->constructWebhookEvent($payload, $signature);
        } catch (SignatureVerificationException $e) {
            Log::warning('Rejected Stripe webhook: invalid signature', ['reason' => $e->getMessage()]);

            throw StripeWebhookException::invalidSignature();
        } catch (\UnexpectedValueException $e) {
            Log::warning('Rejected Stripe webhook: invalid payload', ['reason' => $e->getMessage()]);

            throw StripeWebhookException::invalidPayload();
        }

        Log::info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        // Stripe delivers events at least once, so the same event can arrive twice.
        if (! $this->recordEvent($event)) {
            Log::info('Duplicate Stripe event skipped', ['id' => $event->id]);

            return;
        }

        try {
            $this->dispatchWebhookJob($event);
        } catch (\Throwable $e) {
            // Forget the event so that Stripe's retry is processed instead of skipped.
            StripeEvent::where('event_id', $event->id)->delete();

            throw $e;
        }
    }

    /**
     * Record the event ID. Returns false if it was already recorded.
     *
     * The unique index on event_id makes this safe when two deliveries of the
     * same event arrive at the same time: only one insert can succeed.
     */
    private function recordEvent(\Stripe\Event $event): bool
    {
        $inserted = StripeEvent::query()->insertOrIgnore([
            'event_id' => $event->id,
            'type' => $event->type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $inserted === 1;
    }

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