<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Services\InvoiceService;
use App\Domains\Subscription\Services\FeatureGateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for billing-related operations
 * 
 * @OA\Tag(
 *     name="Billing",
 *     description="Billing and invoice management endpoints"
 * )
 */
class BillingController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly FeatureGateService $featureGateService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/billing/invoices",
     *     summary="Get user's invoice history",
     *     tags={"Billing"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of invoices to retrieve (default: 10, max: 100)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string", example="in_1234567890"),
     *                     @OA\Property(property="number", type="string", example="INV-2024-001"),
     *                     @OA\Property(property="amount_paid", type="integer", example=2999),
     *                     @OA\Property(property="amount_due", type="integer", example=0),
     *                     @OA\Property(property="currency", type="string", example="USD"),
     *                     @OA\Property(property="status", type="string", example="paid"),
     *                     @OA\Property(property="created", type="integer", example=1640995200),
     *                     @OA\Property(property="due_date", type="integer", example=1640995200),
     *                     @OA\Property(property="hosted_invoice_url", type="string", example="https://invoice.stripe.com/i/..."),
     *                     @OA\Property(property="invoice_pdf", type="string", example="https://pay.stripe.com/invoice/.../pdf"),
     *                     @OA\Property(property="description", type="string", example="Pro Plan - Monthly")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function invoices(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 10);
        $invoices = $this->invoiceService->getInvoiceHistory($request->user(), $limit);

        return response()->json([
            'data' => $invoices,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/billing/upcoming-invoice",
     *     summary="Get upcoming invoice preview",
     *     tags={"Billing"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Upcoming invoice retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 oneOf={
     *                     @OA\Schema(
     *                         @OA\Property(property="amount_due", type="integer", example=2999),
     *                         @OA\Property(property="currency", type="string", example="USD"),
     *                         @OA\Property(property="period_start", type="integer", example=1640995200),
     *                         @OA\Property(property="period_end", type="integer", example=1643673600),
     *                         @OA\Property(property="next_payment_attempt", type="integer", example=1643673600),
     *                         @OA\Property(property="description", type="string", example="Pro Plan - Monthly")
     *                     ),
     *                     @OA\Schema(type="null")
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function upcomingInvoice(Request $request): JsonResponse
    {
        $invoice = $this->invoiceService->getUpcomingInvoice($request->user());

        return response()->json([
            'data' => $invoice,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/billing/portal",
     *     summary="Create billing portal session",
     *     tags={"Billing"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="return_url",
     *                 type="string",
     *                 format="url",
     *                 example="https://yourapp.com/billing",
     *                 description="URL to redirect to after billing portal session"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Billing portal session created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="portal_url",
     *                 type="string",
     *                 format="url",
     *                 example="https://billing.stripe.com/session/bps_1234567890",
     *                 description="URL to Stripe billing portal"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Unable to create billing portal session",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unable to create billing portal session")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function billingPortal(Request $request): JsonResponse
    {
        $returnUrl = $request->input('return_url', config('app.frontend_url', config('app.url')));
        
        $portalUrl = $this->invoiceService->createBillingPortalSession(
            $request->user(),
            $returnUrl
        );

        if (!$portalUrl) {
            return response()->json([
                'message' => 'Unable to create billing portal session',
            ], 400);
        }

        return response()->json([
            'portal_url' => $portalUrl,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/billing/usage",
     *     summary="Get user's usage summary",
     *     tags={"Billing"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Usage summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="api_calls",
     *                     type="object",
     *                     @OA\Property(property="current", type="integer", example=1250),
     *                     @OA\Property(property="limit", type="integer", example=10000),
     *                     @OA\Property(property="percentage", type="number", format="float", example=12.5),
     *                     @OA\Property(property="unlimited", type="boolean", example=false)
     *                 ),
     *                 @OA\Property(
     *                     property="storage_mb",
     *                     type="object",
     *                     @OA\Property(property="current", type="integer", example=450),
     *                     @OA\Property(property="limit", type="integer", example=1000),
     *                     @OA\Property(property="percentage", type="number", format="float", example=45.0),
     *                     @OA\Property(property="unlimited", type="boolean", example=false)
     *                 ),
     *                 @OA\Property(
     *                     property="team_members",
     *                     type="object",
     *                     @OA\Property(property="current", type="integer", example=3),
     *                     @OA\Property(property="limit", type="integer", example=5),
     *                     @OA\Property(property="percentage", type="number", format="float", example=60.0),
     *                     @OA\Property(property="unlimited", type="boolean", example=false)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function usage(Request $request): JsonResponse
    {
        $usage = $this->featureGateService->getUsageSummary($request->user());

        return response()->json([
            'data' => $usage,
        ]);
    }
}