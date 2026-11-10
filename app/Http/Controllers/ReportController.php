<?php

namespace App\Http\Controllers;

use App\Domains\Subscription\Services\FeatureGateService;
use App\Domains\Subscription\Services\UsageTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A plan-gated endpoint. The route applies two middleware:
 * - feature:advanced_analytics returns 403 unless the user's plan includes the feature
 *   (Basic and Pro do; the free tier does not), see config/features.php
 * - usage:api_calls returns 429 once the monthly api_calls limit is reached, and
 *   records one api_call for every successful response
 *
 * @OA\Tag(
 *     name="Reports",
 *     description="Plan-gated reporting endpoints"
 * )
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly UsageTrackingService $usageTrackingService,
        private readonly FeatureGateService $featureGateService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/reports/usage",
     *     summary="Daily API usage for the last 30 days (requires a plan with advanced_analytics)",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Usage report",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="feature", type="string", example="api_calls"),
     *                 @OA\Property(property="daily", type="object", example={"2026-11-09": 12, "2026-11-10": 4}),
     *                 @OA\Property(property="current_month", type="integer", example=16),
     *                 @OA\Property(property="limit", type="integer", nullable=true, example=10000)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Current plan does not include advanced_analytics"),
     *     @OA\Response(response=429, description="Monthly api_calls limit reached")
     * )
     */
    public function usage(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'feature' => 'api_calls',
                'daily' => $this->usageTrackingService->getUsageHistory($user, 'api_calls'),
                'current_month' => $this->featureGateService->getCurrentUsage($user, 'api_calls'),
                'limit' => $this->featureGateService->getUsageLimit($user, 'api_calls'),
            ],
        ]);
    }
}
