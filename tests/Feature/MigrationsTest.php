<?php

use App\Enums\BillingInterval;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('old monthly and yearly interval values are converted to month and year', function () {
    $plan = Plan::factory()->create();
    DB::table('plan_prices')->insert([
        ['plan_id' => $plan->id, 'price' => 100, 'currency' => 'usd', 'interval' => 'monthly'],
        ['plan_id' => $plan->id, 'price' => 1000, 'currency' => 'usd', 'interval' => 'yearly'],
    ]);

    $migration = require database_path('migrations/2026_11_10_000004_standardise_plan_price_intervals.php');
    $migration->up();

    expect(PlanPrice::orderBy('price')->pluck('interval')->all())
        ->toBe([BillingInterval::MONTH, BillingInterval::YEAR]);
});

test('a subscription cannot point at a plan that does not exist', function () {
    $price = PlanPrice::factory()->create();

    Subscription::create([
        'user_id' => User::factory()->create()->id,
        'plan_id' => 999,
        'plan_price_id' => $price->id,
        'stripe_subscription_id' => 'sub_orphan',
        'stripe_status' => 'active',
    ]);
})->throws(QueryException::class);

test('a plan price that has subscriptions cannot be deleted', function () {
    $user = subscribedUser();

    $user->subscriptions()->first()->price->delete();
})->throws(QueryException::class);
