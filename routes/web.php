<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Default Stripe Checkout redirect targets, used when FRONTEND_URL points at this app.
Route::view('/subscription/success', 'checkout.result', [
    'title' => 'Payment received',
    'message' => 'Thank you. Your subscription will be active as soon as Stripe confirms the payment, usually within a few seconds.',
])->name('checkout.success');

Route::view('/subscription/cancel', 'checkout.result', [
    'title' => 'Checkout cancelled',
    'message' => 'No payment was taken. You can choose a plan again at any time.',
])->name('checkout.cancel');
