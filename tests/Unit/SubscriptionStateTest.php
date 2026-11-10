<?php

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;

function subscriptionWith(SubscriptionStatus $status, ?DateTimeInterface $endsAt = null): Subscription
{
    return new Subscription(['stripe_status' => $status, 'ends_at' => $endsAt]);
}

test('only active and trialing statuses count as active', function (SubscriptionStatus $status, bool $active) {
    expect($status->isActive())->toBe($active)
        ->and(subscriptionWith($status)->isActive())->toBe($active);
})->with([
    'active' => [SubscriptionStatus::ACTIVE, true],
    'trialing' => [SubscriptionStatus::TRIALING, true],
    'past_due' => [SubscriptionStatus::PAST_DUE, false],
    'unpaid' => [SubscriptionStatus::UNPAID, false],
    'incomplete' => [SubscriptionStatus::INCOMPLETE, false],
    'paused' => [SubscriptionStatus::PAUSED, false],
    'canceled without an end date' => [SubscriptionStatus::CANCELED, false],
]);

test('a cancelled subscription is still active until its end date', function () {
    $this->freezeSecond(); // the datetime cast stores whole seconds
    $subscription = subscriptionWith(SubscriptionStatus::CANCELED, now()->addDays(3));

    expect($subscription->isActive())->toBeTrue()
        ->and($subscription->isOnGracePeriod())->toBeTrue()
        ->and($subscription->hasExpired())->toBeFalse()
        ->and($subscription->gracePeriodDaysRemaining())->toBe(3);
});

test('a cancelled subscription past its end date has expired', function () {
    $subscription = subscriptionWith(SubscriptionStatus::CANCELED, now()->subDay());

    expect($subscription->isActive())->toBeFalse()
        ->and($subscription->isOnGracePeriod())->toBeFalse()
        ->and($subscription->hasExpired())->toBeTrue()
        ->and($subscription->gracePeriodDaysRemaining())->toBe(0);
});

test('an active subscription is neither on a grace period nor expired', function () {
    $subscription = subscriptionWith(SubscriptionStatus::ACTIVE, now()->addMonth());

    expect($subscription->isCanceled())->toBeFalse()
        ->and($subscription->isOnGracePeriod())->toBeFalse()
        ->and($subscription->hasExpired())->toBeFalse();
});

test('an unknown status string cannot be stored', function () {
    new Subscription(['stripe_status' => 'made_up']);
})->throws(ValueError::class);

test('the active scope returns only active and trialing subscriptions', function () {
    foreach (SubscriptionStatus::cases() as $status) {
        Subscription::factory()->status($status)->create();
    }

    expect(Subscription::active()->pluck('stripe_status')->all())
        ->toEqualCanonicalizing([SubscriptionStatus::ACTIVE, SubscriptionStatus::TRIALING]);
});
