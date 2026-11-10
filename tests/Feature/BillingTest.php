<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('billing portal return_url', function () {
    beforeEach(function () {
        config(['app.url' => 'http://localhost:8000', 'app.frontend_url' => 'https://app.example.com']);
        Sanctum::actingAs(User::factory()->create(['stripe_customer_id' => 'cus_test']));
    });

    test('a return_url on another host is rejected', function (string $url) {
        $this->postJson('/api/billing/portal', ['return_url' => $url])
            ->assertStatus(422)
            ->assertJsonValidationErrors('return_url');
    })->with([
        'another site' => 'https://evil.example.net/billing',
        'look-alike host' => 'https://app.example.com.evil.net/',
        'javascript scheme' => 'javascript:alert(1)',
    ]);

    test('a return_url on the frontend or API host is accepted', function (string $url) {
        $this->stripe->shouldReceive('createBillingPortalSession')->once()->with('cus_test', $url)
            ->andReturn(\Stripe\BillingPortal\Session::constructFrom(['url' => 'https://billing.stripe.test/session']));

        $this->postJson('/api/billing/portal', ['return_url' => $url])
            ->assertOk()
            ->assertExactJson(['portal_url' => 'https://billing.stripe.test/session']);
    })->with([
        'frontend' => 'https://app.example.com/billing',
        'API' => 'http://localhost:8000/account',
    ]);

    test('without a return_url the portal returns to FRONTEND_URL', function () {
        $this->stripe->shouldReceive('createBillingPortalSession')->once()->with('cus_test', 'https://app.example.com')
            ->andReturn(\Stripe\BillingPortal\Session::constructFrom(['url' => 'https://billing.stripe.test/session']));

        $this->postJson('/api/billing/portal')->assertOk();
    });
});

test('the billing portal returns 400 for a user who has never paid', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/billing/portal')
        ->assertStatus(400)
        ->assertJsonPath('message', 'Unable to create billing portal session');
});

describe('invoice history', function () {
    test('the invoice limit must be between 1 and 100', function (int $limit) {
        Sanctum::actingAs(User::factory()->create(['stripe_customer_id' => 'cus_test']));

        $this->getJson("/api/billing/invoices?limit={$limit}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('limit');
    })->with([0, 101]);

    test('invoices are fetched from Stripe with the requested limit', function () {
        Sanctum::actingAs(User::factory()->create(['stripe_customer_id' => 'cus_test']));
        $this->stripe->shouldReceive('listInvoices')->once()->with('cus_test', 50)->andReturn($this->stripeList([[
            'id' => 'in_1', 'number' => 'INV-1', 'amount_paid' => 2000, 'amount_due' => 0, 'currency' => 'usd',
            'status' => 'paid', 'created' => 1, 'due_date' => null, 'hosted_invoice_url' => null,
            'invoice_pdf' => null, 'lines' => ['data' => [['description' => 'Basic']]],
        ]]));

        $this->getJson('/api/billing/invoices?limit=50')
            ->assertOk()
            ->assertJsonPath('data.0.currency', 'USD')
            ->assertJsonPath('data.0.description', 'Basic');
    });

    test('a user with no Stripe customer has no invoices and Stripe is not called', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/billing/invoices')->assertOk()->assertExactJson(['data' => []]);
    });
});

describe('upcoming invoice', function () {
    test('the next invoice preview is returned', function () {
        Sanctum::actingAs(subscribedUser());
        $this->stripe->shouldReceive('previewUpcomingInvoice')->once()->with('cus_test', 'sub_test')
            ->andReturn(\Stripe\Invoice::constructFrom([
                'amount_due' => 2000, 'currency' => 'usd', 'period_start' => 1, 'period_end' => 2,
                'next_payment_attempt' => null, 'lines' => ['data' => [['description' => 'Basic']]],
            ]));

        $this->getJson('/api/billing/upcoming-invoice')
            ->assertOk()
            ->assertJsonPath('data.amount_due', 2000)
            ->assertJsonPath('data.currency', 'USD');
    });

    test('nothing to preview returns null data, not an error', function () {
        Sanctum::actingAs(subscribedUser());
        $this->stripe->shouldReceive('previewUpcomingInvoice')->andReturnNull();

        $this->getJson('/api/billing/upcoming-invoice')->assertOk()->assertExactJson(['data' => null]);
    });

    test('a user with no Stripe customer gets null data without a Stripe call', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/billing/upcoming-invoice')->assertOk()->assertExactJson(['data' => null]);
    });

    test('a Stripe outage returns 502 with a generic message, never 500', function () {
        Sanctum::actingAs(subscribedUser());
        $this->stripe->shouldReceive('previewUpcomingInvoice')
            ->andThrow(new \Stripe\Exception\ApiConnectionException('Could not connect to api.stripe.com'));

        $response = $this->getJson('/api/billing/upcoming-invoice')
            ->assertStatus(502)
            ->assertExactJson(['message' => 'The payment provider could not complete the request. Please try again later.']);

        expect($response->getContent())->not->toContain('api.stripe.com');
    });
});
