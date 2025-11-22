<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\StripeController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::middleware('auth:sanctum')->group(function () {

    // Plans
    Route::get('/plans', [SubscriptionController::class, 'listPlans']);

    // Subscription Management
    Route::prefix('subscription')->group(function () {
        Route::get('/status', [SubscriptionController::class, 'status']);
        Route::post('/checkout', [SubscriptionController::class, 'createCheckoutSession']);
        Route::post('/cancel', [SubscriptionController::class, 'cancelSubscription']);

        //Stripe Checkout Redirects
        Route::get('/success', [SubscriptionController::class, 'success']);
        Route::get('/cancel', [SubscriptionController::class, 'cancel']);
    });

    Route::post('/logout', [AuthController::class, 'logout']);
});

// Stripe Webhook
Route::post('/stripe/webhook', [StripeController::class, 'handle']);
