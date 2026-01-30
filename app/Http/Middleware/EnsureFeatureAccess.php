<?php

namespace App\Http\Middleware;

use App\Domains\Subscription\Services\FeatureGateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure user has access to specific features
 * 
 * This middleware checks feature access based on the user's
 * subscription plan and feature configuration.
 */
class EnsureFeatureAccess
{
    public function __construct(
        private readonly FeatureGateService $featureGateService
    ) {}

    /**
     * Handle an incoming request
     * 
     * @param string $feature The feature slug to check access for
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Authentication required',
            ], 401);
        }

        if (!$this->featureGateService->hasFeatureAccess($user, $feature)) {
            return response()->json([
                'message' => 'This feature requires a subscription upgrade',
                'feature' => $feature,
                'upgrade_url' => route('plans.index'),
            ], 403);
        }

        return $next($request);
    }
}