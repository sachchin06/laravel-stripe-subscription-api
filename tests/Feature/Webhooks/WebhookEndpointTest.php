<?php

use App\Domains\Webhooks\Jobs\HandleCheckoutSessionCompleted;
use App\Domains\Webhooks\Jobs\HandleInvoicePaid;
use App\Domains\Webhooks\Jobs\HandleInvoicePaymentFailed;
use App\Domains\Webhooks\Jobs\HandleSubscriptionDeleted;
use App\Domains\Webhooks\Jobs\HandleSubscriptionUpdated;
use App\Models\StripeEvent;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

describe('signature verification', function () {
    beforeEach(fn () => Queue::fake());

    test('a request without a Stripe-Signature header is rejected with 400', function () {
        $this->postJson('/api/webhooks/stripe', $this->stripeEvent('invoice.paid', ['id' => 'in_1']))
            ->assertStatus(400)
            ->assertExactJson(['message' => 'Invalid webhook signature.']);

        Queue::assertNothingPushed();
    });

    test('a request signed with the wrong secret is rejected with 400', function () {
        $this->postStripeWebhook($this->stripeEvent('invoice.paid', ['id' => 'in_1']), secret: 'whsec_wrong')
            ->assertStatus(400)
            ->assertExactJson(['message' => 'Invalid webhook signature.']);

        Queue::assertNothingPushed();
        expect(StripeEvent::count())->toBe(0);
    });

    test('a correctly signed body that is not valid JSON is rejected with 400', function () {
        $payload = '{not json';
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", config('stripe.webhook.secret'));

        $this->call('POST', '/api/webhooks/stripe', server: [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'HTTP_ACCEPT' => 'application/json',
        ], content: $payload)
            ->assertStatus(400)
            ->assertExactJson(['message' => 'Invalid webhook payload.']);
    });
});

describe('dispatching', function () {
    beforeEach(fn () => Queue::fake());

    test('a valid signed event returns 200 and queues the matching job', function (string $type, string $job) {
        $this->postStripeWebhook($this->stripeEvent($type, ['id' => 'obj_1']))
            ->assertOk()
            ->assertExactJson(['message' => 'Webhook received']);

        Queue::assertPushed($job, 1);
    })->with([
        'checkout.session.completed' => ['checkout.session.completed', HandleCheckoutSessionCompleted::class],
        'customer.subscription.created' => ['customer.subscription.created', HandleSubscriptionUpdated::class],
        'customer.subscription.updated' => ['customer.subscription.updated', HandleSubscriptionUpdated::class],
        'customer.subscription.deleted' => ['customer.subscription.deleted', HandleSubscriptionDeleted::class],
        'invoice.paid' => ['invoice.paid', HandleInvoicePaid::class],
        'invoice.payment_failed' => ['invoice.payment_failed', HandleInvoicePaymentFailed::class],
    ]);

    test('an event type the app does not handle is acknowledged without queuing a job', function () {
        $this->postStripeWebhook($this->stripeEvent('customer.created', ['id' => 'cus_1']))->assertOk();

        Queue::assertNothingPushed();
    });

    test('the same event ID delivered twice queues the job only once', function () {
        $event = $this->stripeEvent('invoice.paid', ['id' => 'in_1'], id: 'evt_duplicate');

        $this->postStripeWebhook($event)->assertOk();
        $this->postStripeWebhook($event)->assertOk();

        Queue::assertPushed(HandleInvoicePaid::class, 1);
        expect(StripeEvent::where('event_id', 'evt_duplicate')->count())->toBe(1);
    });
});

test('a server fault returns 500 so Stripe retries, and the event is not marked as seen', function () {
    config(['app.debug' => false, 'queue.default' => 'database']);
    Schema::drop('jobs'); // queuing the job will now fail

    $response = $this->postStripeWebhook($this->stripeEvent('invoice.paid', ['id' => 'in_1']));

    $response->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
    expect(StripeEvent::count())->toBe(0);
});
