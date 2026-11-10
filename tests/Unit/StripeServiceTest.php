<?php

use App\Domains\Shared\Services\StripeService;
use Tests\Support\FakeStripeHttpClient;

/*
| These tests run the real StripeService and Stripe SDK. FakeStripeHttpClient
| replaces only the HTTP layer, so no request leaves the machine.
*/

test('every request uses the pinned API version', function () {
    $http = FakeStripeHttpClient::install(fn () => [['id' => 'cus_1', 'object' => 'customer']]);

    (new StripeService)->retrieveCustomer('cus_1');

    expect((new StripeService)->client()->getStripeVersion())->toBe('2025-10-29.clover')
        ->and($http->header(0, 'Stripe-Version'))->toBe('2025-10-29.clover');
});

test('create calls send the given idempotency key', function () {
    $http = FakeStripeHttpClient::install(fn () => [['id' => 'cus_1', 'object' => 'customer']]);

    (new StripeService)->createCustomer(['email' => 'ada@example.com'], 'customer-1-1700000000-new');

    expect($http->requests[0]['method'])->toBe('post')
        ->and($http->requests[0]['path'])->toBe('/v1/customers')
        ->and($http->header(0, 'Idempotency-Key'))->toBe('customer-1-1700000000-new');
});

test('create calls without a natural key still send a random idempotency key', function () {
    $http = FakeStripeHttpClient::install(fn () => [['id' => 'cs_1', 'object' => 'checkout.session']]);

    (new StripeService)->createCheckoutSession(['mode' => 'subscription']);

    expect($http->header(0, 'Idempotency-Key'))->toMatch('/^[0-9a-f-]{36}$/');
});

test('the upcoming invoice is previewed with invoices/create_preview', function () {
    $http = FakeStripeHttpClient::install(fn () => [['object' => 'invoice', 'amount_due' => 2000]]);

    $invoice = (new StripeService)->previewUpcomingInvoice('cus_1', 'sub_1');

    expect($invoice->amount_due)->toBe(2000)
        ->and($http->requests[0]['path'])->toBe('/v1/invoices/create_preview')
        ->and($http->requests[0]['params'])->toBe(['customer' => 'cus_1', 'subscription' => 'sub_1']);
});

test('nothing to preview returns null instead of throwing', function () {
    FakeStripeHttpClient::install(fn () => [
        ['error' => ['type' => 'invalid_request_error', 'message' => 'No upcoming invoices for customer']],
        400,
    ]);

    expect((new StripeService)->previewUpcomingInvoice('cus_1'))->toBeNull();
});

test('webhook events are only accepted with a valid signature', function () {
    $payload = json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => 'invoice.paid', 'data' => ['object' => []]]);
    $timestamp = time();
    $valid = 't='.$timestamp.',v1='.hash_hmac('sha256', "{$timestamp}.{$payload}", config('stripe.webhook.secret'));

    expect((new StripeService)->constructWebhookEvent($payload, $valid)->id)->toBe('evt_1');

    (new StripeService)->constructWebhookEvent($payload, "t={$timestamp},v1=".str_repeat('0', 64));
})->throws(\Stripe\Exception\SignatureVerificationException::class);
