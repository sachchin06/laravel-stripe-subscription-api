<?php

namespace App\Http\Middleware;

use App\Domains\Subscription\Services\SubscriptionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure user has an active subscription
 * 
 * This middleware checks if the authenticated user has
 * an active subscription before allowing access.
 */
class EnsureUserHasActiveSubscription
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager
    ) {}

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Authentication required',
            ], 401);
        }

        if (!$this->subscriptionManager->hasActiveSubscription($user)) {
            return response()->json([
                'message' => 'Active subscription required',
                'subscribe_url' => route('plans.index'),
            ], 403);
        }

        return $next($request);
    }
}
