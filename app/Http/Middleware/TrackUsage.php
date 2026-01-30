<?php

namespace App\Http\Middleware;

use App\Domains\Subscription\Services\FeatureGateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to track feature usage and enforce limits
 */
class TrackUsage
{
    public function __construct(
        private readonly FeatureGateService $featureGateService
    ) {}

    /**
     * Handle an incoming request
     * 
     * @param string $feature The feature to track usage for
     * @param int $amount The amount of usage to record (default: 1)
     */
    public function handle(Request $request, Closure $next, string $feature, int $amount = 1): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Authentication required',
            ], 401);
        }

        // Check if user can perform this action
        if (!$this->featureGateService->canPerformAction($user, $feature, $amount)) {
            $current = $this->featureGateService->getCurrentUsage($user, $feature);
            $limit = $this->featureGateService->getUsageLimit($user, $feature);

            return response()->json([
                'message' => 'Usage limit exceeded',
                'feature' => $feature,
                'current_usage' => $current,
                'limit' => $limit,
                'upgrade_url' => route('plans.index'),
            ], 429);
        }

        // Process the request
        $response = $next($request);

        // Record usage only if request was successful
        if ($response->getStatusCode() < 400) {
            $this->featureGateService->recordUsage($user, $feature, $amount, [
                'endpoint' => $request->path(),
                'method' => $request->method(),
            ]);
        }

        return $response;
    }
}