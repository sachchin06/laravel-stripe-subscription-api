<?php

namespace App\Exceptions;

use Exception;

class StripeWebhookException extends Exception
{
    public static function invalidSignature(string $message): self
    {
        return new self("Invalid webhook signature: {$message}", 400);
    }

    public static function processingFailed(string $reason): self
    {
        return new self("Webhook processing failed: {$reason}", 400);
    }
}
