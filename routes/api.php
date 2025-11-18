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

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/plans', [SubscriptionController::class, 'listPlans']);

    // Create Checkout Session
    Route::post('/subscribe', [SubscriptionController::class, 'createCheckout']);

    Route::get('/subscription/success', [SubscriptionController::class, 'success']);
    Route::get('/subscription/cancel', [SubscriptionController::class, 'cancel']);

    // View subscription status
    Route::get('/subscription', [SubscriptionController::class, 'status']);

    // Cancel subscription
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancelSubscription']);
});

Route::post('/stripe/webhook', [StripeController::class, 'handle']);
