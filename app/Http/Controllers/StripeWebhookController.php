<?php

namespace App\Http\Controllers;

use App\Jobs\Webhooks\HandleCheckoutSessionCompleted;
use App\Jobs\Webhooks\HandleSubscriptionDeleted;
use App\Jobs\Webhooks\HandleSubscriptionUpdated;
use App\Services\Stripe\StripeWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeWebhookService $webhookService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        Log::info('Webhook endpoint hit', [
            'method' => $request->method(),
            'has_signature' => $request->hasHeader('Stripe-Signature'),
            'content_length' => strlen($request->getContent()),
        ]);

        try {
            $event = $this->webhookService->constructEvent(
                payload: $request->getContent(),
                signature: $request->header('Stripe-Signature')
            );

            Log::info('Stripe webhook received', [
                'type' => $event->type,
                'id' => $event->id,
            ]);

            match ($event->type) {
                'checkout.session.completed' => HandleCheckoutSessionCompleted::dispatch($event->data->object->toArray()),
                'customer.subscription.updated' => HandleSubscriptionUpdated::dispatch($event->data->object->toArray()),
                'customer.subscription.deleted' => HandleSubscriptionDeleted::dispatch($event->data->object->toArray()),
                default => Log::info('Unhandled Stripe event type', ['type' => $event->type]),
            };

            Log::info('Webhook job dispatched successfully');

            return response()->json(['message' => 'Webhook received']);
        } catch (\Exception $e) {
            Log::error('Stripe webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Webhook error: ' . $e->getMessage(),
            ], 400);
        }
    }
}
