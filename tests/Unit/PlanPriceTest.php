<?php

use App\Enums\BillingInterval;
use App\Models\Plan;
use App\Models\PlanPrice;

test('prices are stored and returned as integer cents', function () {
    $price = PlanPrice::factory()->create(['price' => 2000]);

    expect($price->fresh()->price)->toBe(2000);
});

test('the formatted price converts cents to a currency amount', function (int $cents, string $currency, string $formatted) {
    expect((new PlanPrice(['price' => $cents, 'currency' => $currency]))->formatted_price)->toBe($formatted);
})->with([
    [2000, 'usd', '20.00 USD'],
    [32000, 'gbp', '320.00 GBP'],
    [999, 'eur', '9.99 EUR'],
]);

test('the interval is cast to the BillingInterval enum', function () {
    $monthly = PlanPrice::factory()->create();
    $yearly = PlanPrice::factory()->yearly()->create();

    expect($monthly->fresh()->interval)->toBe(BillingInterval::MONTH)
        ->and($monthly->isMonthly())->toBeTrue()
        ->and($monthly->isYearly())->toBeFalse()
        ->and($yearly->fresh()->interval)->toBe(BillingInterval::YEAR)
        ->and($yearly->isYearly())->toBeTrue();
});

test('the interval values are the ones Stripe uses', function () {
    expect(BillingInterval::from('month'))->toBe(BillingInterval::MONTH)
        ->and(BillingInterval::from('year'))->toBe(BillingInterval::YEAR)
        ->and(BillingInterval::tryFrom('monthly'))->toBeNull()
        ->and(BillingInterval::tryFrom('week'))->toBeNull();
});

test('a plan finds its monthly and yearly prices', function () {
    $plan = Plan::factory()->create();
    $monthly = PlanPrice::factory()->for($plan)->create();
    $yearly = PlanPrice::factory()->for($plan)->yearly()->create();

    expect($plan->getMonthlyPrice()->is($monthly))->toBeTrue()
        ->and($plan->getYearlyPrice()->is($yearly))->toBeTrue();
});

test('the active scope hides archived plans', function () {
    Plan::factory()->create(['slug' => 'current']);
    Plan::factory()->inactive()->create(['slug' => 'archived']);

    expect(Plan::active()->pluck('slug')->all())->toBe(['current']);
});
