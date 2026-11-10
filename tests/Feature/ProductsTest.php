<?php

use App\Domains\Shared\Services\StripeService;
use App\Enums\BillingInterval;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Mockery as m;
use Tests\Support\FakeStripeHttpClient;

beforeEach(fn () => Sanctum::actingAs(User::factory()->admin()->create()));

describe('creating and updating', function () {
    test('a product is saved locally first, then created in Stripe with keys from the local rows', function () {
        $this->stripe->shouldReceive('createProduct')->once()
            ->with(['name' => 'Team', 'metadata' => ['slug' => 'team']], m::pattern('/^product-\d+-\d+$/'))
            ->andReturn(\Stripe\Product::constructFrom(['id' => 'prod_team']));
        $this->stripe->shouldReceive('createPrice')->once()
            ->with(m::on(fn (array $p) => $p['product'] === 'prod_team'
                && $p['unit_amount'] === 5000
                && $p['recurring']['interval'] === 'year'), m::pattern('/^price-\d+-\d+$/'))
            ->andReturn(\Stripe\Price::constructFrom(['id' => 'price_team_year']));

        $this->postJson('/api/products', ['name' => 'Team', 'prices' => [['interval' => 'year', 'price' => 5000]]])
            ->assertCreated()
            ->assertJsonPath('data.stripe_product_id', 'prod_team')
            ->assertJsonPath('data.prices.0.stripe_price_id', 'price_team_year');
    });

    test('if Stripe fails part-way the local rows remain and stripe:sync to finishes with the same keys', function () {
        $keys = [];
        $this->stripe->shouldReceive('createProduct')->once()->andReturn(\Stripe\Product::constructFrom(['id' => 'prod_team']));
        $this->stripe->shouldReceive('createPrice')->twice()->andReturnUsing(function (array $params, string $key) use (&$keys) {
            $keys[] = $key;
            if (count($keys) === 1) {
                throw new \Stripe\Exception\ApiConnectionException('Network error');
            }

            return \Stripe\Price::constructFrom(['id' => 'price_team_month']);
        });

        $this->postJson('/api/products', ['name' => 'Team', 'prices' => [['interval' => 'month', 'price' => 5000]]])
            ->assertStatus(502);

        $price = Plan::where('slug', 'team')->first()->prices()->first();
        expect($price->stripe_price_id)->toBeNull();

        $this->artisan('stripe:sync', ['direction' => 'to'])->assertSuccessful();

        expect($keys[1])->toBe($keys[0])
            ->and($price->fresh()->stripe_price_id)->toBe('price_team_month');
    });

    test('the product interval must be month or year', function () {
        $this->postJson('/api/products', ['name' => 'Team', 'prices' => [['interval' => 'monthly', 'price' => 5000]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('prices.0.interval');
    });

    test('updating a product changes it in Stripe and locally', function () {
        $plan = Plan::factory()->create(['stripe_product_id' => 'prod_basic', 'name' => 'Basic']);
        $this->stripe->shouldReceive('updateProduct')->once()
            ->with('prod_basic', ['name' => 'Basic Plus', 'description' => $plan->description]);

        $this->putJson("/api/products/{$plan->id}", ['name' => 'Basic Plus'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Basic Plus');
    });

    test('adding a price creates it in Stripe for the plan\'s product', function () {
        $plan = Plan::factory()->create(['stripe_product_id' => 'prod_basic']);
        $this->stripe->shouldReceive('createPrice')->once()
            ->with(m::on(fn (array $p) => $p['product'] === 'prod_basic'), m::type('string'))
            ->andReturn(\Stripe\Price::constructFrom(['id' => 'price_new']));

        $this->postJson("/api/products/{$plan->id}/prices", ['interval' => 'month', 'price' => 999])
            ->assertCreated()
            ->assertJsonPath('data.stripe_price_id', 'price_new');
    });
});

describe('archiving', function () {
    test('archiving a plan nobody has used archives it in Stripe and deletes it', function () {
        $price = PlanPrice::factory()->create();
        $this->stripe->shouldReceive('archiveProduct')->once()->with($price->plan->stripe_product_id);
        $this->stripe->shouldReceive('archivePrice')->once()->with($price->stripe_price_id);

        $this->deleteJson("/api/products/{$price->plan_id}")->assertOk();

        expect(Plan::count())->toBe(0);
    });

    test('archiving a plan with subscribers marks it inactive, hides it and keeps their access', function () {
        $subscriber = subscribedUser('basic');
        $plan = Plan::where('slug', 'basic')->first();
        $this->stripe->shouldReceive('archiveProduct')->once();
        $this->stripe->shouldReceive('archivePrice')->once();

        $this->deleteJson("/api/products/{$plan->id}")->assertOk();

        expect($plan->fresh()->is_active)->toBeFalse()
            ->and($subscriber->hasActiveSubscription())->toBeTrue()
            ->and($subscriber->canAccessFeature('advanced_analytics'))->toBeTrue();
        $this->getJson('/api/plans')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/products')->assertOk()->assertJsonPath('data.0.is_active', false);
    });
});

describe('syncing with Stripe', function () {
    test('the sync direction must be from or to', function () {
        $this->postJson('/api/products/sync', ['direction' => 'sideways'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('direction');
    });

    test('a Stripe error during sync returns 502 without Stripe\'s message', function () {
        config(['app.debug' => false]);
        $this->stripe->shouldReceive('listProducts')
            ->andThrow(new \Stripe\Exception\AuthenticationException('Invalid API Key provided: sk_test_***'));

        $response = $this->postJson('/api/products/sync', ['direction' => 'from'])->assertStatus(502);

        expect($response->getContent())->not->toContain('API Key');
    });

    test('the seeded plans can be created in Stripe with stripe:sync to', function () {
        $this->seed();
        $this->stripe->shouldReceive('createProduct')->twice()
            ->andReturnUsing(fn (array $params) => \Stripe\Product::constructFrom(['id' => 'prod_'.$params['metadata']['slug']]));
        $this->stripe->shouldReceive('createPrice')->times(4)
            ->andReturnUsing(fn (array $params) => \Stripe\Price::constructFrom([
                'id' => "price_{$params['product']}_{$params['recurring']['interval']}",
            ]));

        $this->artisan('stripe:sync', ['direction' => 'to'])
            ->expectsOutputToContain('Synced to Stripe: 2 products, 4 prices')
            ->expectsOutputToContain('price_prod_basic_month')
            ->assertSuccessful();

        expect(PlanPrice::whereNull('stripe_price_id')->count())->toBe(0);
    });

    test('stripe:sync from links seeded plans and gives clashing new plans a unique slug', function () {
        $this->seed();
        Plan::where('slug', 'pro')->update(['stripe_product_id' => 'prod_other']);

        $this->stripe->shouldReceive('listProducts')->andReturn($this->stripeList([
            ['id' => 'prod_basic', 'object' => 'product', 'name' => 'Basic', 'description' => null, 'metadata' => []],
            ['id' => 'prod_pro_2', 'object' => 'product', 'name' => 'Pro', 'description' => null, 'metadata' => ['slug' => 'pro']],
        ]));
        $this->stripe->shouldReceive('listPricesForProduct')->with('prod_basic')->andReturn($this->stripeList([
            ['id' => 'price_basic_month', 'object' => 'price', 'type' => 'recurring', 'unit_amount' => 2000,
                'currency' => 'usd', 'recurring' => ['interval' => 'month']],
        ]));
        $this->stripe->shouldReceive('listPricesForProduct')->with('prod_pro_2')->andReturn($this->stripeList([]));

        $this->artisan('stripe:sync', ['direction' => 'from'])->assertSuccessful();

        $basic = Plan::where('slug', 'basic')->first();
        expect($basic->stripe_product_id)->toBe('prod_basic')
            ->and($basic->getMonthlyPrice()->stripe_price_id)->toBe('price_basic_month')
            ->and($basic->prices()->count())->toBe(2)
            ->and(Plan::where('slug', 'pro-2')->value('stripe_product_id'))->toBe('prod_pro_2');
    });

    test('stripe:sync from reads every page of products from Stripe', function () {
        $http = FakeStripeHttpClient::install(function (string $method, string $path, array $params) {
            if ($path !== '/v1/products') {
                return [['object' => 'list', 'url' => $path, 'has_more' => false, 'data' => []]];
            }
            $secondPage = ($params['starting_after'] ?? null) === 'prod_1';

            return [[
                'object' => 'list',
                'url' => '/v1/products',
                'has_more' => ! $secondPage,
                'data' => [[
                    'id' => $secondPage ? 'prod_2' : 'prod_1',
                    'object' => 'product',
                    'name' => $secondPage ? 'Two' : 'One',
                    'description' => null,
                    'metadata' => [],
                ]],
            ]];
        });
        $this->app->instance(StripeService::class, new StripeService);

        $this->artisan('stripe:sync', ['direction' => 'from'])->assertSuccessful();

        expect(Plan::orderBy('id')->pluck('slug')->all())->toBe(['one', 'two']);
        $productRequests = array_values(array_filter($http->requests, fn ($r) => $r['path'] === '/v1/products'));
        expect($productRequests)->toHaveCount(2)
            ->and($productRequests[1]['params']['starting_after'])->toBe('prod_1');
    });

    test('daily and weekly Stripe prices are skipped and yearly ones are mapped', function () {
        $this->stripe->shouldReceive('listProducts')->andReturn($this->stripeList([
            ['id' => 'prod_x', 'object' => 'product', 'name' => 'X', 'description' => null, 'metadata' => []],
        ]));
        $this->stripe->shouldReceive('listPricesForProduct')->andReturn($this->stripeList([
            ['id' => 'price_year', 'object' => 'price', 'type' => 'recurring', 'unit_amount' => 9900, 'currency' => 'usd', 'recurring' => ['interval' => 'year']],
            ['id' => 'price_week', 'object' => 'price', 'type' => 'recurring', 'unit_amount' => 100, 'currency' => 'usd', 'recurring' => ['interval' => 'week']],
            ['id' => 'price_once', 'object' => 'price', 'type' => 'one_time', 'unit_amount' => 100, 'currency' => 'usd', 'recurring' => null],
        ]));

        $this->postJson('/api/products/sync', ['direction' => 'from'])->assertOk()->assertJsonPath('synced.prices', 1);

        expect(PlanPrice::sole())
            ->stripe_price_id->toBe('price_year')
            ->interval->toBe(BillingInterval::YEAR);
    });
});
