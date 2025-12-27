<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
});

// Stripe webhook
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe');

Route::middleware('auth:sanctum')->group(function () {
  
    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/user', [AuthController::class, 'user'])->name('auth.user');
    });

    // Plans
    Route::get('/plans', [SubscriptionController::class, 'listPlans'])
        ->name('plans.index');

    // Subscriptions
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'status'])->name('status');
        Route::post('/checkout', [SubscriptionController::class, 'createCheckoutSession'])->name('checkout');
        Route::post('/cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
    });
});
