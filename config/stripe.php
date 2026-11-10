<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe API Keys
    |--------------------------------------------------------------------------
    |
    | The Stripe publishable and secret keys from Stripe dashboard.
    |
    */

    'key' => env('STRIPE_KEY'),

    'secret' => env('STRIPE_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Stripe Webhook Secret
    |--------------------------------------------------------------------------
    |
    | The webhook secret is used to verify that webhooks are sent from Stripe.
    | You can find this in your Stripe dashboard under Developers > Webhooks.
    |
    */

    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe API Version
    |--------------------------------------------------------------------------
    |
    | Pinned API version, passed explicitly to every StripeClient. The code
    | relies on the field layout of this version (e.g. current_period_* on
    | subscription items), so change it only together with the code.
    |
    */

    'api_version' => env('STRIPE_API_VERSION', '2025-10-29.clover'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The default currency for your application.
    |
    */

    'currency' => env('STRIPE_CURRENCY', 'usd'),

];
