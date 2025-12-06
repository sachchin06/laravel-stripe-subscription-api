<?php

namespace App\Services\Stripe;

use App\Exceptions\StripeWebhookException;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookService
{
    public function constructEvent(string $payload, string $signature): Event
    {
        $secret = config('stripe.webhook.secret');

        try {
            return Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException $e) {
            throw StripeWebhookException::invalidSignature($e->getMessage());
        } catch (\Exception $e) {
            throw StripeWebhookException::invalidPayload($e->getMessage());
        }
    }
}
