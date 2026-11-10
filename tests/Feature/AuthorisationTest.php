<?php

use App\Models\Plan;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

dataset('protected routes', [
    'GET /api/auth/user' => ['GET', '/api/auth/user'],
    'POST /api/auth/logout' => ['POST', '/api/auth/logout'],
    'GET /api/plans' => ['GET', '/api/plans'],
    'GET /api/subscriptions' => ['GET', '/api/subscriptions'],
    'GET /api/subscriptions/details' => ['GET', '/api/subscriptions/details'],
    'POST /api/subscriptions/checkout' => ['POST', '/api/subscriptions/checkout'],
    'POST /api/subscriptions/cancel' => ['POST', '/api/subscriptions/cancel'],
    'POST /api/subscriptions/resume' => ['POST', '/api/subscriptions/resume'],
    'POST /api/subscriptions/change-plan' => ['POST', '/api/subscriptions/change-plan'],
    'GET /api/billing/invoices' => ['GET', '/api/billing/invoices'],
    'GET /api/billing/upcoming-invoice' => ['GET', '/api/billing/upcoming-invoice'],
    'POST /api/billing/portal' => ['POST', '/api/billing/portal'],
    'GET /api/billing/usage' => ['GET', '/api/billing/usage'],
    'GET /api/reports/usage' => ['GET', '/api/reports/usage'],
    'GET /api/products' => ['GET', '/api/products'],
]);

dataset('products routes', [
    'list' => ['GET', '/api/products'],
    'create' => ['POST', '/api/products'],
    'show' => ['GET', '/api/products/{plan}'],
    'update' => ['PUT', '/api/products/{plan}'],
    'archive' => ['DELETE', '/api/products/{plan}'],
    'add price' => ['POST', '/api/products/{plan}/prices'],
    'sync' => ['POST', '/api/products/sync'],
]);

test('guests get 401 on protected routes', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with('protected routes');

test('non-admins get 403 on every products route', function (string $method, string $uri) {
    $plan = Plan::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->json($method, str_replace('{plan}', (string) $plan->id, $uri))->assertForbidden();
})->with('products routes');

test('admins can use the products routes', function () {
    $plan = Plan::factory()->create(['name' => 'Basic']);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson('/api/products')->assertOk()->assertJsonPath('data.0.name', 'Basic');
    $this->getJson("/api/products/{$plan->id}")->assertOk()->assertJsonPath('data.id', $plan->id);
});
