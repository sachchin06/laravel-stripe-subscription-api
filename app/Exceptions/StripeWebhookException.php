<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * A webhook request that did not come from Stripe or could not be read.
 * Always a 400: Stripe stops retrying, and the response reveals no detail.
 */
class StripeWebhookException extends Exception
{
    public static function invalidSignature(): self
    {
        return new self('Invalid webhook signature.', 400);
    }

    public static function invalidPayload(): self
    {
        return new self('Invalid webhook payload.', 400);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 400);
    }
}
