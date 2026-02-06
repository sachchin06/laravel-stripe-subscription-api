<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ProductController;
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

// Stripe webhook (no auth required)
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
  
    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/user', [AuthController::class, 'user'])->name('auth.user');
    });

    // Plans (public listing)
    Route::get('/plans', [SubscriptionController::class, 'listPlans'])->name('plans.index');

    // Subscriptions
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'status'])->name('subscriptions.status');
        Route::get('/details', [SubscriptionController::class, 'details'])->name('subscriptions.details');
        Route::post('/checkout', [SubscriptionController::class, 'createCheckoutSession'])->name('subscriptions.checkout');
        Route::post('/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
        Route::post('/resume', [SubscriptionController::class, 'resume'])->name('subscriptions.resume');
        Route::post('/change-plan', [SubscriptionController::class, 'changePlan'])->name('subscriptions.change-plan');
    });

    // Billing
    Route::prefix('billing')->group(function () {
        Route::get('/invoices', [BillingController::class, 'invoices'])->name('billing.invoices');
        Route::get('/upcoming-invoice', [BillingController::class, 'upcomingInvoice'])->name('billing.upcoming-invoice');
        Route::post('/portal', [BillingController::class, 'billingPortal'])->name('billing.portal');
        Route::get('/usage', [BillingController::class, 'usage'])->name('billing.usage');
    });

    // Products/Plans Management (Admin)
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('products.index');
        Route::post('/', [ProductController::class, 'store'])->name('products.store');
        Route::get('/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::put('/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/{product}/prices', [ProductController::class, 'addPrice'])->name('products.prices.store');
        Route::post('/sync', [ProductController::class, 'sync'])->name('products.sync');
    });
});
