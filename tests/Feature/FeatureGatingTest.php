<?php

use App\Models\UsageRecord;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('the free tier gets 403 from /api/reports/usage and no usage is recorded', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/reports/usage')
        ->assertForbidden()
        ->assertJsonPath('feature', 'advanced_analytics');

    expect(UsageRecord::count())->toBe(0);
});

test('a Basic subscriber can use the report and each successful call is recorded', function () {
    $user = subscribedUser('basic');
    Sanctum::actingAs($user);

    $this->getJson('/api/reports/usage')->assertOk()->assertJsonPath('data.limit', 10000);
    $this->getJson('/api/reports/usage')->assertOk()->assertJsonPath('data.current_month', 1);

    expect((int) UsageRecord::where('user_id', $user->id)->where('feature', 'api_calls')->sum('amount'))->toBe(2);
});

test('going over the monthly limit returns 429 and is not recorded', function () {
    $user = subscribedUser('basic');
    UsageRecord::create(['user_id' => $user->id, 'feature' => 'api_calls', 'amount' => 10000, 'recorded_at' => now()]);
    Sanctum::actingAs($user);

    $this->getJson('/api/reports/usage')
        ->assertStatus(429)
        ->assertJsonPath('current_usage', 10000)
        ->assertJsonPath('limit', 10000);

    expect(UsageRecord::count())->toBe(1);
});

test('a plan missing from config/features.php gets free-tier limits', function () {
    Sanctum::actingAs(subscribedUser('enterprise'));

    $this->getJson('/api/billing/usage')
        ->assertOk()
        ->assertJsonPath('data.api_calls.limit', 100)
        ->assertJsonPath('data.api_calls.unlimited', false);
});

test('a Pro subscriber has unlimited api_calls', function () {
    Sanctum::actingAs(subscribedUser('pro'));

    $this->getJson('/api/billing/usage')
        ->assertOk()
        ->assertJsonPath('data.api_calls.limit', null)
        ->assertJsonPath('data.api_calls.unlimited', true);
});
