<?php

use App\Domains\Subscription\Services\SubscriptionManager;
use App\Domains\Webhooks\Jobs\HandleCheckoutSessionCompleted;
use App\Domains\Webhooks\Jobs\HandleInvoicePaid;
use App\Domains\Webhooks\Jobs\HandleInvoicePaymentFailed;
use App\Domains\Webhooks\Jobs\HandleSubscriptionDeleted;
use App\Domains\Webhooks\Jobs\HandleSubscriptionUpdated;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;

function invoiceFor(string $subscriptionId): array
{
    return [
        'id' => 'in_test',
        'object' => 'invoice',
        'parent' => ['subscription_details' => ['subscription' => $subscriptionId]],
    ];
}

describe('checkout.session.completed', function () {
    test('handling the same checkout twice creates one subscription', function () {
        $price = PlanPrice::factory()->create(['stripe_price_id' => 'price_test']);
        $user = User::factory()->create();
        $session = ['id' => 'cs_1', 'subscription' => 'sub_test', 'client_reference_id' => (string) $user->id];

        // Stripe is asked once: the second run finds the stored row first.
        $this->stripe->shouldReceive('retrieveSubscription')->once()->with('sub_test')
            ->andReturn($this->stripeSubscription());

        HandleCheckoutSessionCompleted::dispatchSync($session);
        HandleCheckoutSessionCompleted::dispatchSync($session);

        expect(Subscription::count())->toBe(1);
        expect(Subscription::first())
            ->user_id->toBe($user->id)
            ->plan_price_id->toBe($price->id)
            ->stripe_status->toBe(SubscriptionStatus::ACTIVE);
        expect($user->fresh()->stripe_customer_id)->toBe('cus_test');
    });

    test('a row inserted by a concurrent worker is returned instead of a duplicate', function () {
        $price = PlanPrice::factory()->create(['stripe_price_id' => 'price_test']);
        $user = User::factory()->create();
        $existing = Subscription::factory()->for($user)->forPrice($price)->create(['stripe_subscription_id' => 'sub_test']);

        // createOrFirst hits the unique index and returns the row already there.
        $result = Subscription::createOrFirst(
            ['stripe_subscription_id' => 'sub_test'],
            ['user_id' => $user->id, 'plan_id' => $price->plan_id, 'plan_price_id' => $price->id],
        );

        expect($result->is($existing))->toBeTrue()
            ->and($result->wasRecentlyCreated)->toBeFalse()
            ->and(Subscription::count())->toBe(1);
    });

    test('a second active subscription is stored and a warning is logged', function () {
        $user = subscribedUser();
        PlanPrice::factory()->create(['stripe_price_id' => 'price_test']);
        Log::spy();

        $this->stripe->shouldReceive('retrieveSubscription')->with('sub_second')
            ->andReturn($this->stripeSubscription(['id' => 'sub_second']));

        app(SubscriptionManager::class)->createFromCheckoutSession([
            'id' => 'cs_2', 'subscription' => 'sub_second', 'client_reference_id' => (string) $user->id,
        ]);

        expect($user->subscriptions()->active()->count())->toBe(2);
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => $message === 'User has more than one active subscription')
            ->once();
    });

    test('a session without a client_reference_id is skipped without calling Stripe', function () {
        HandleCheckoutSessionCompleted::dispatchSync(['id' => 'cs_1', 'subscription' => 'sub_test']);

        expect(Subscription::count())->toBe(0);
    });
});

describe('state comes from Stripe, not from the event', function () {
    test('subscription.deleted followed by a late invoice.paid leaves the subscription cancelled', function () {
        $user = subscribedUser();
        $cancelled = $this->stripeSubscription(['status' => 'canceled', 'ended_at' => 1_765_324_800]);
        $this->stripe->shouldReceive('retrieveSubscription')->twice()->with('sub_test')->andReturn($cancelled);

        HandleSubscriptionDeleted::dispatchSync(['id' => 'sub_test', 'status' => 'canceled']);
        HandleInvoicePaid::dispatchSync(invoiceFor('sub_test'));

        $subscription = $user->subscriptions()->first();
        expect($subscription->stripe_status)->toBe(SubscriptionStatus::CANCELED)
            ->and($subscription->ends_at->getTimestamp())->toBe(1_765_324_800);
    });

    test('a stale status in the payload is ignored in favour of the status in Stripe', function () {
        $user = subscribedUser();
        $this->stripe->shouldReceive('retrieveSubscription')->andReturn($this->stripeSubscription([
            'items' => ['data' => [['price' => ['id' => 'price_basic_month']]]],
        ]));

        HandleSubscriptionUpdated::dispatchSync(['id' => 'sub_test', 'status' => 'past_due']);

        expect($user->subscriptions()->first()->stripe_status)->toBe(SubscriptionStatus::ACTIVE);
    });

    test('a plan change made in the Billing Portal updates the local plan and price', function () {
        $user = subscribedUser('basic');
        $pro = PlanPrice::factory()->for(Plan::factory()->slug('pro'))->create(['stripe_price_id' => 'price_pro_month']);
        $this->stripe->shouldReceive('retrieveSubscription')->andReturn($this->stripeSubscription([
            'items' => ['data' => [['price' => ['id' => 'price_pro_month']]]],
        ]));

        HandleSubscriptionUpdated::dispatchSync(['id' => 'sub_test']);

        expect($user->subscriptions()->first())
            ->plan_price_id->toBe($pro->id)
            ->plan->slug->toBe('pro');
    });

    test('cancelling at period end stores the end of the current period', function () {
        $user = subscribedUser();
        $this->stripe->shouldReceive('retrieveSubscription')->andReturn($this->stripeSubscription([
            'cancel_at_period_end' => true,
            'items' => ['data' => [['price' => ['id' => 'price_basic_month']]]],
        ]));

        HandleSubscriptionUpdated::dispatchSync(['id' => 'sub_test']);

        expect($user->subscriptions()->first()->ends_at->getTimestamp())->toBe(1_765_324_800);
    });

    test('invoice.payment_failed records the status Stripe reports', function () {
        $user = subscribedUser();
        $this->stripe->shouldReceive('retrieveSubscription')->andReturn($this->stripeSubscription([
            'status' => 'past_due',
            'items' => ['data' => [['price' => ['id' => 'price_basic_month']]]],
        ]));

        HandleInvoicePaymentFailed::dispatchSync(invoiceFor('sub_test'));

        expect($user->subscriptions()->first()->stripe_status)->toBe(SubscriptionStatus::PAST_DUE);
    });

    test('a price unknown locally updates the status but leaves the plan unchanged', function () {
        $user = subscribedUser();
        $before = $user->subscriptions()->first();
        $this->stripe->shouldReceive('retrieveSubscription')->andReturn($this->stripeSubscription([
            'status' => 'past_due',
            'items' => ['data' => [['price' => ['id' => 'price_created_in_dashboard']]]],
        ]));

        HandleSubscriptionUpdated::dispatchSync(['id' => 'sub_test']);

        expect($before->fresh())
            ->stripe_status->toBe(SubscriptionStatus::PAST_DUE)
            ->plan_price_id->toBe($before->plan_price_id);
    });

    test('an invoice that is not for a subscription is ignored', function () {
        HandleInvoicePaid::dispatchSync(['id' => 'in_one_off', 'parent' => null]);

        expect(Subscription::count())->toBe(0);
    });

    test('an event for a subscription not stored locally is skipped without calling Stripe', function () {
        HandleSubscriptionUpdated::dispatchSync(['id' => 'sub_unknown']);

        expect(Subscription::count())->toBe(0);
    });
});
