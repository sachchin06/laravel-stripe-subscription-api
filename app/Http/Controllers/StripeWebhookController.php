<?php

namespace App\Http\Controllers;

use App\Domains\Webhooks\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly WebhookService $webhookService
    ) {}

    /**
     * Error responses come from StripeWebhookException (400) or the global
     * exception handler (5xx), configured in bootstrap/app.php.
     */
    public function handle(Request $request): JsonResponse
    {
        $this->webhookService->processWebhook(
            payload: $request->getContent(),
            signature: $request->header('Stripe-Signature')
        );

        return response()->json(['message' => 'Webhook received']);
    }
}
