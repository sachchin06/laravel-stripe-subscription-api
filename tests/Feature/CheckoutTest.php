<?php

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Mockery as m;

beforeEach(function () {
    PlanPrice::factory()->for(Plan::factory()->slug('pro'))->create(['stripe_price_id' => 'price_pro_month']);
});

test('checkout rejects an unknown price with 422', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/subscriptions/checkout', ['price_id' => 'price_unknown'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('price_id');
});

test('checkout rejects a price on an archived plan', function () {
    PlanPrice::factory()->for(Plan::factory()->inactive())->create(['stripe_price_id' => 'price_archived']);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/subscriptions/checkout', ['price_id' => 'price_archived'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This plan is no longer available.');
});

test('checkout is refused with 409 when the user already has an active subscription', function () {
    Sanctum::actingAs(subscribedUser('basic'));

    $this->postJson('/api/subscriptions/checkout', ['price_id' => 'price_pro_month'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'You already have an active subscription. To switch plans, use POST /api/subscriptions/change-plan.');
});

test('checkout expires the user\'s open sessions and returns the new Checkout URL', function () {
    $user = User::factory()->create(['stripe_customer_id' => 'cus_test']);
    Sanctum::actingAs($user);

    $this->stripe->shouldReceive('retrieveCustomer')->with('cus_test')
        ->andReturn(\Stripe\Customer::constructFrom(['id' => 'cus_test']));
    $this->stripe->shouldReceive('listOpenCheckoutSessions')->with('cus_test')
        ->andReturn($this->stripeList([['id' => 'cs_old_1'], ['id' => 'cs_old_2']]));
    $this->stripe->shouldReceive('expireCheckoutSession')->once()->with('cs_old_1');
    $this->stripe->shouldReceive('expireCheckoutSession')->once()->with('cs_old_2');
    $this->stripe->shouldReceive('createCheckoutSession')->once()
        ->with(m::on(fn (array $params) => $params['mode'] === 'subscription'
            && $params['customer'] === 'cus_test'
            && $params['client_reference_id'] === (string) $user->id
            && $params['line_items'][0]['price'] === 'price_pro_month'
            && $params['success_url'] === 'http://localhost/subscription/success?session_id={CHECKOUT_SESSION_ID}'))
        ->andReturn(\Stripe\Checkout\Session::constructFrom(['id' => 'cs_new', 'url' => 'https://checkout.stripe.test/cs_new']));

    $this->postJson('/api/subscriptions/checkout', ['price_id' => 'price_pro_month'])
        ->assertOk()
        ->assertExactJson(['checkout_url' => 'https://checkout.stripe.test/cs_new']);
});

test('a session that completes while being expired does not block the new checkout', function () {
    Sanctum::actingAs(User::factory()->create(['stripe_customer_id' => 'cus_test']));

    $this->stripe->shouldReceive('retrieveCustomer')->andReturn(\Stripe\Customer::constructFrom(['id' => 'cus_test']));
    $this->stripe->shouldReceive('listOpenCheckoutSessions')->andReturn($this->stripeList([['id' => 'cs_old']]));
    $this->stripe->shouldReceive('expireCheckoutSession')
        ->andThrow(new \Stripe\Exception\InvalidRequestException('Session is not open'));
    $this->stripe->shouldReceive('createCheckoutSession')
        ->andReturn(\Stripe\Checkout\Session::constructFrom(['url' => 'https://checkout.stripe.test/cs_new']));

    $this->postJson('/api/subscriptions/checkout', ['price_id' => 'price_pro_month'])->assertOk();
});

test('a new Stripe customer is created with an idempotency key stable for the user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->stripe->shouldReceive('createCustomer')->once()
        ->with(m::type('array'), "customer-{$user->id}-{$user->created_at->getTimestamp()}-new")
        ->andReturn(\Stripe\Customer::constructFrom(['id' => 'cus_new']));
    $this->stripe->shouldReceive('listOpenCheckoutSessions')->andReturn($this->stripeList([]));
    $this->stripe->shouldReceive('createCheckoutSession')
        ->andReturn(\Stripe\Checkout\Session::constructFrom(['url' => 'https://checkout.stripe.test/cs_new']));

    $this->postJson('/api/subscriptions/checkout', ['price_id' => 'price_pro_month'])->assertOk();

    expect($user->fresh()->stripe_customer_id)->toBe('cus_new');
});

test('a deleted Stripe customer is replaced with a new one', function () {
    $user = User::factory()->create(['stripe_customer_id' => 'cus_deleted']);
    Sanctum::actingAs($user);

    $this->stripe->shouldReceive('retrieveCustomer')
        ->andReturn(\Stripe\Customer::constructFrom(['id' => 'cus_deleted', 'deleted' => true]));
    $this->stripe->shouldReceive('createCustomer')->once()
        ->with(m::type('array'), m::pattern('/-cus_deleted$/'))
        ->andReturn(\Stripe\Customer::constructFrom(['id' => 'cus_replacement']));
    $this->stripe->shouldReceive('listOpenCheckoutSessions')->andReturn($this->stripeList([]));
    $this->stripe->shouldReceive('createCheckoutSession')
        ->andReturn(\Stripe\Checkout\Session::constructFrom(['url' => 'https://checkout.stripe.test/cs_new']));

    $this->postJson('/api/subscriptions/checkout', ['price_id' => 'price_pro_month'])->assertOk();

    expect($user->fresh()->stripe_customer_id)->toBe('cus_replacement');
});

test('the default success and cancel pages are served', function () {
    $this->get('/subscription/success')->assertOk()->assertSee('Payment received');
    $this->get('/subscription/cancel')->assertOk()->assertSee('Checkout cancelled');
});
