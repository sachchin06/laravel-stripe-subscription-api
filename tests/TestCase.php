<?php

namespace Tests;

use App\Domains\Shared\Services\StripeService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Stripe\ApiRequestor;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test gets a Mockery mock in place of StripeService. All Stripe
     * access goes through that class, so a test can only "call Stripe" by
     * declaring an expectation on this mock; any unexpected call fails the
     * test instead of reaching the network.
     */
    protected MockInterface $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripe = $this->mock(StripeService::class);
    }

    protected function tearDown(): void
    {
        // Undo FakeStripeHttpClient::install() for tests that used the real SDK.
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    /**
     * POST an event to the webhook endpoint with a valid Stripe-Signature.
     *
     * Stripe signs "{timestamp}.{raw body}" with HMAC-SHA256 using the endpoint
     * secret and sends "t={timestamp},v1={signature}". Building the header the
     * same way means the real verification code runs in tests.
     */
    protected function postStripeWebhook(array $event, ?string $secret = null): TestResponse
    {
        $payload = json_encode($event);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret ?? config('stripe.webhook.secret'));

        return $this->call('POST', '/api/webhooks/stripe', server: [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: $payload);
    }

    protected function stripeEvent(string $type, array $object, string $id = 'evt_test_1'): array
    {
        return [
            'id' => $id,
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $object],
        ];
    }

    /**
     * A Stripe subscription as the API returns it (2025-10-29.clover layout).
     */
    protected function stripeSubscription(array $overrides = []): \Stripe\Subscription
    {
        return \Stripe\Subscription::constructFrom(array_replace_recursive([
            'id' => 'sub_test',
            'object' => 'subscription',
            'customer' => 'cus_test',
            'status' => 'active',
            'trial_end' => null,
            'cancel_at' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'ended_at' => null,
            'default_payment_method' => null,
            'items' => [
                'object' => 'list',
                'has_more' => false,
                'data' => [[
                    'id' => 'si_test',
                    'object' => 'subscription_item',
                    'current_period_start' => 1_762_732_800,
                    'current_period_end' => 1_765_324_800,
                    'price' => [
                        'id' => 'price_test',
                        'object' => 'price',
                        'unit_amount' => 2000,
                        'currency' => 'usd',
                        'recurring' => ['interval' => 'month'],
                    ],
                ]],
            ],
        ], $overrides));
    }

    /**
     * A one-page Stripe list, as returned by the list endpoints.
     */
    protected function stripeList(array $items): \Stripe\Collection
    {
        return \Stripe\Collection::constructFrom([
            'object' => 'list',
            'url' => '/v1/test',
            'has_more' => false,
            'data' => $items,
        ]);
    }
}
