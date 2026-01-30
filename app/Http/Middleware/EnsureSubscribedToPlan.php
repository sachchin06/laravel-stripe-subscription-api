<?php

namespace App\Http\Middleware;

use App\Domains\Subscription\Services\SubscriptionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure user is subscribed to a specific plan
 */
class EnsureSubscribedToPlan
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager
    ) {}

    /**
     * Handle an incoming request
     * 
     * @param string $planSlug The plan slug to check subscription for
     */
    public function handle(Request $request, Closure $next, string $planSlug): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Authentication required',
            ], 401);
        }

        $subscription = $this->subscriptionManager->getActiveSubscription($user);

        if (!$subscription || $subscription->plan->slug !== $planSlug) {
            return response()->json([
                'message' => "This feature requires a {$planSlug} subscription",
                'required_plan' => $planSlug,
                'current_plan' => $subscription?->plan->slug,
                'upgrade_url' => route('plans.index'),
            ], 403);
        }

        return $next($request);
    }
}