<?php

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Every test runs real migrations on an in-memory SQLite database, which is
| rolled back after each test. StripeService is mocked in Tests\TestCase.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * A user with one subscription to a plan with the given slug.
 * "basic" and "pro" are the plans configured in config/features.php.
 */
function subscribedUser(
    string $planSlug = 'basic',
    SubscriptionStatus $status = SubscriptionStatus::ACTIVE,
    array $subscription = [],
): User {
    $price = PlanPrice::factory()
        ->for(Plan::factory()->slug($planSlug))
        ->create(['stripe_price_id' => "price_{$planSlug}_month"]);

    $user = User::factory()->create(['stripe_customer_id' => 'cus_test']);

    Subscription::factory()
        ->for($user)
        ->forPrice($price)
        ->status($status)
        ->create(['stripe_subscription_id' => 'sub_test'] + $subscription);

    return $user;
}
