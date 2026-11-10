<?php

use App\Exceptions\StripeWebhookException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'subscribed' => \App\Http\Middleware\EnsureUserHasActiveSubscription::class,
            'feature' => \App\Http\Middleware\EnsureFeatureAccess::class,
            'plan' => \App\Http\Middleware\EnsureSubscribedToPlan::class,
            'usage' => \App\Http\Middleware\TrackUsage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API clients always receive JSON errors. With APP_DEBUG=false (production),
        // unexpected errors become a plain "Server Error" with no internal detail.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // Rejected webhooks are expected traffic, not application errors.
        $exceptions->dontReport(StripeWebhookException::class);

        // Stripe errors are logged in full, but the client only learns that the
        // payment provider failed, never Stripe's own error text.
        $exceptions->render(function (ApiErrorException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'The payment provider could not complete the request. Please try again later.',
                ], 502);
            }
        });
    })->create();
