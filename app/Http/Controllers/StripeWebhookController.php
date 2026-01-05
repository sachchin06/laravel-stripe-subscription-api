<?php

namespace App\Http\Controllers;

use App\Domains\Webhooks\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for handling Stripe webhook events
 * 
 * This controller provides a thin HTTP layer for webhook processing,
 * delegating the actual work to the WebhookService.
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly WebhookService $webhookService
    ) {}

    /**
     * Handle incoming Stripe webhook
     */
    public function handle(Request $request): JsonResponse
    {
        Log::info('Stripe webhook endpoint hit', [
            'method' => $request->method(),
            'has_signature' => $request->hasHeader('Stripe-Signature'),
            'content_length' => strlen($request->getContent()),
        ]);

        try {
            $this->webhookService->processWebhook(
                payload: $request->getContent(),
                signature: $request->header('Stripe-Signature')
            );

            return response()->json(['message' => 'Webhook processed successfully']);

        } catch (\Exception $e) {
            Log::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Webhook processing failed: ' . $e->getMessage(),
            ], 400);
        }
    }
}
