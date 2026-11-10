<?php

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('status and details', function () {
    test('status returns the active subscription with its plan and price', function () {
        Sanctum::actingAs(subscribedUser('basic', SubscriptionStatus::TRIALING));

        $this->getJson('/api/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.status', 'trialing')
            ->assertJsonPath('data.plan.slug', 'basic')
            ->assertJsonPath('data.price.price', 2000);
    });

    test('status returns null when the user has no active subscription', function () {
        Sanctum::actingAs(subscribedUser('basic', SubscriptionStatus::CANCELED));

        $this->getJson('/api/subscriptions')->assertOk()->assertExactJson(['data' => null]);
    });

    test('details read the billing period from the subscription item', function () {
        Sanctum::actingAs(subscribedUser());
        $this->stripe->shouldReceive('retrieveSubscription')->with('sub_test')->andReturn($this->stripeSubscription());

        $this->getJson('/api/subscriptions/details')
            ->assertOk()
            ->assertJsonPath('data.current_period_start', 1_762_732_800)
            ->assertJsonPath('data.current_period_end', 1_765_324_800)
            ->assertJsonPath('data.plan.interval', 'month');
    });
});

describe('cancel', function () {
    test('cancelling sets cancel_at_period_end in Stripe and stores Stripe\'s end date', function () {
        $user = subscribedUser();
        Sanctum::actingAs($user);
        $this->stripe->shouldReceive('updateSubscription')->once()
            ->with('sub_test', ['cancel_at_period_end' => true])
            ->andReturn($this->stripeSubscription(['cancel_at_period_end' => true, 'cancel_at' => 1_765_324_800]));

        $this->postJson('/api/subscriptions/cancel')
            ->assertOk()
            ->assertJsonPath('message', 'Subscription will be cancelled at the end of the billing period');

        expect($user->subscriptions()->first()->ends_at->getTimestamp())->toBe(1_765_324_800);
    });

    test('cancelling without an active subscription returns 404', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/subscriptions/cancel')->assertNotFound();
    });
});

describe('resume', function () {
    test('resuming clears the scheduled cancellation in Stripe and locally', function () {
        $user = subscribedUser(subscription: ['ends_at' => now()->addWeek()]);
        Sanctum::actingAs($user);
        $this->stripe->shouldReceive('resumeSubscription')->once()->with('sub_test');

        $this->postJson('/api/subscriptions/resume')->assertOk()->assertJsonPath('data.ends_at', null);

        expect($user->subscriptions()->first()->ends_at)->toBeNull();
    });

    test('resuming a subscription that is not scheduled to end returns 400', function () {
        Sanctum::actingAs(subscribedUser());

        $this->postJson('/api/subscriptions/resume')
            ->assertStatus(400)
            ->assertJsonPath('message', 'Subscription is not scheduled for cancellation');
    });
});

describe('change plan', function () {
    beforeEach(function () {
        PlanPrice::factory()->for(Plan::factory()->slug('pro'))->create(['stripe_price_id' => 'price_pro_month']);
    });

    test('changing plan updates the price in Stripe and the local plan and price', function () {
        $user = subscribedUser('basic');
        Sanctum::actingAs($user);
        $this->stripe->shouldReceive('changeSubscriptionPrice')->once()->with('sub_test', 'price_pro_month');

        $this->postJson('/api/subscriptions/change-plan', ['price_id' => 'price_pro_month'])
            ->assertOk()
            ->assertJsonPath('data.plan.slug', 'pro');

        expect($user->subscriptions()->first()->price->stripe_price_id)->toBe('price_pro_month');
    });

    test('changing to the price the user already has is rejected with 422', function () {
        Sanctum::actingAs(subscribedUser('basic'));

        $this->postJson('/api/subscriptions/change-plan', ['price_id' => 'price_basic_month'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price_id' => 'You are already subscribed to this price.']);
    });

    test('changing to an unknown price is rejected with 422', function () {
        Sanctum::actingAs(subscribedUser());

        $this->postJson('/api/subscriptions/change-plan', ['price_id' => 'price_unknown'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('price_id');
    });

    test('changing to a price on an archived plan is rejected with 422', function () {
        PlanPrice::factory()->for(Plan::factory()->inactive())->create(['stripe_price_id' => 'price_archived']);
        Sanctum::actingAs(subscribedUser());

        $this->postJson('/api/subscriptions/change-plan', ['price_id' => 'price_archived'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This plan is no longer available.');
    });

    test('changing plan without an active subscription returns 404', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/subscriptions/change-plan', ['price_id' => 'price_pro_month'])->assertNotFound();
    });
});
