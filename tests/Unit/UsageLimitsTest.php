<?php

use App\Domains\Subscription\Services\UsageTrackingService;
use App\Enums\SubscriptionStatus;
use App\Models\UsageRecord;
use App\Models\User;

beforeEach(function () {
    $this->usage = app(UsageTrackingService::class);
});

test('limits follow the user\'s plan in config/features.php', function (?string $plan, string $feature, ?int $limit) {
    $user = $plan ? subscribedUser($plan) : User::factory()->create();

    expect($this->usage->getUserLimit($user, $feature))->toBe($limit);
})->with([
    'free tier' => [null, 'api_calls', 100],
    'basic' => ['basic', 'api_calls', 10000],
    'pro (null means unlimited)' => ['pro', 'api_calls', null],
    'pro, limited feature' => ['pro', 'storage_mb', 10000],
    'plan missing from config gets free limits' => ['enterprise', 'api_calls', 100],
]);

test('a feature missing from a configured plan gets the free limit', function () {
    config(['features.limits.plans.basic' => ['storage_mb' => 1000]]);

    expect($this->usage->getUserLimit(subscribedUser('basic'), 'api_calls'))->toBe(100);
});

test('a cancelled subscription falls back to free limits', function () {
    $user = subscribedUser('pro', SubscriptionStatus::CANCELED);

    expect($this->usage->getUserLimit($user, 'api_calls'))->toBe(100);
});

test('an action is allowed up to the limit and refused beyond it', function () {
    $user = User::factory()->create();
    UsageRecord::create(['user_id' => $user->id, 'feature' => 'api_calls', 'amount' => 99, 'recorded_at' => now()]);

    expect($this->usage->canPerformAction($user, 'api_calls'))->toBeTrue()
        ->and($this->usage->canPerformAction($user, 'api_calls', 2))->toBeFalse();
});

test('only usage from the current month counts', function () {
    $user = User::factory()->create();
    UsageRecord::create(['user_id' => $user->id, 'feature' => 'api_calls', 'amount' => 500, 'recorded_at' => now()->subMonthNoOverflow()]);
    $this->usage->recordUsage($user, 'api_calls', 3);

    expect($this->usage->getCurrentMonthUsage($user, 'api_calls'))->toBe(3);
});

test('the usage summary reports current use, limit and percentage', function () {
    $user = User::factory()->create();
    $this->usage->recordUsage($user, 'api_calls', 25);

    expect($this->usage->getUsageSummary($user)['api_calls'])->toBe([
        'current' => 25,
        'limit' => 100,
        'percentage' => 25.0,
        'unlimited' => false,
    ]);
});
