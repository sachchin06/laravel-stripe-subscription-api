<?php

namespace App\Exceptions;

use Exception;

class StripeWebhookException extends Exception
{
    public static function invalidSignature(string $message): self
    {
        return new self("Invalid webhook signature: {$message}", 400);
    }

    public static function invalidPayload(string $message): self
    {
        return new self("Invalid webhook payload: {$message}", 400);
    }
}
