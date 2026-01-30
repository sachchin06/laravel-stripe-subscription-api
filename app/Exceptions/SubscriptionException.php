<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class SubscriptionException extends Exception
{
    public static function alreadySubscribed(): self
    {
        return new self('You already have an active subscription for this plan.', 400);
    }

    public static function notActive(): self
    {
        return new self('No active subscription found.', 404);
    }

    public static function notFound(): self
    {
        return new self('Subscription not found.', 404);
    }

    public static function invalidPlanPrice(): self
    {
        return new self('Invalid plan price specified.', 400);
    }

    public static function invalidCheckoutSession(string $reason = 'Invalid checkout session'): self
    {
        return new self($reason, 400);
    }

    public static function cannotCancel(string $reason = 'Subscription cannot be cancelled'): self
    {
        return new self($reason, 400);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], $this->getCode() ?: 400);
    }
}
