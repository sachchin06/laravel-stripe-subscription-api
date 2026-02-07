<?php

namespace App\Http\Controllers;

use App\Domains\Subscription\Services\ProductService;
use App\Domains\Subscription\Services\PlanService;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for product/plan management (admin operations)
 * 
 * @OA\Tag(
 *     name="Products",
 *     description="Product and plan management endpoints"
 * )
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly PlanService $planService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/products",
     *     summary="List all products/plans",
     *     tags={"Products"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of products",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Plan"))
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $plans = $this->planService->getAllPlansWithPrices();

        return response()->json([
            'data' => PlanResource::collection($plans),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/products",
     *     summary="Create a new product/plan with prices",
     *     tags={"Products"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "prices"},
     *             @OA\Property(property="name", type="string", example="Enterprise"),
     *             @OA\Property(property="slug", type="string", example="enterprise"),
     *             @OA\Property(property="description", type="string", example="Enterprise plan with all features"),
     *             @OA\Property(
     *                 property="prices",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="interval", type="string", enum={"monthly", "yearly"}),
     *                     @OA\Property(property="price", type="integer", description="Price in cents", example=9900),
     *                     @OA\Property(property="currency", type="string", example="usd")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Plan"),
     *             @OA\Property(property="message", type="string", example="Product created successfully")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Stripe API error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:plans,slug',
            'description' => 'nullable|string',
            'prices' => 'required|array|min:1',
            'prices.*.interval' => 'required|in:monthly,yearly',
            'prices.*.price' => 'required|integer|min:0',
            'prices.*.currency' => 'nullable|string|size:3',
        ]);

        try {
            $plan = $this->productService->createPlanWithPrices($validated);

            return response()->json([
                'data' => new PlanResource($plan),
                'message' => 'Product created successfully',
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create product', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to create product: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/products/{id}",
     *     summary="Get a specific product/plan",
     *     tags={"Products"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product details",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Plan"))
     *     ),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function show(Plan $product): JsonResponse
    {
        return response()->json([
            'data' => new PlanResource($product->load('prices')),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/products/{id}",
     *     summary="Update a product/plan",
     *     tags={"Products"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Enterprise Plus"),
     *             @OA\Property(property="description", type="string", example="Updated description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Plan"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=500, description="Stripe API error")
     * )
     */
    public function update(Request $request, Plan $product): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $plan = $this->productService->updatePlan($product, $validated);

            return response()->json([
                'data' => new PlanResource($plan->load('prices')),
                'message' => 'Product updated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update product', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to update product: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/products/{id}",
     *     summary="Archive a product/plan",
     *     tags={"Products"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product archived successfully",
     *         @OA\JsonContent(@OA\Property(property="message", type="string"))
     *     ),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=500, description="Stripe API error")
     * )
     */
    public function destroy(Plan $product): JsonResponse
    {
        try {
            $this->productService->archivePlan($product);

            return response()->json([
                'message' => 'Product archived successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to archive product', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to archive product: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/products/{id}/prices",
     *     summary="Add a new price to a product",
     *     tags={"Products"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"interval", "price"},
     *             @OA\Property(property="interval", type="string", enum={"monthly", "yearly"}),
     *             @OA\Property(property="price", type="integer", description="Price in cents", example=4900),
     *             @OA\Property(property="currency", type="string", example="usd")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Price added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/PlanPrice"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=500, description="Stripe API error")
     * )
     */
    public function addPrice(Request $request, Plan $product): JsonResponse
    {
        $validated = $request->validate([
            'interval' => 'required|in:monthly,yearly',
            'price' => 'required|integer|min:0',
            'currency' => 'nullable|string|size:3',
        ]);

        try {
            $price = $this->productService->createPriceForPlan($product, $validated);

            return response()->json([
                'data' => $price,
                'message' => 'Price added successfully',
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to add price', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to add price: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/products/sync",
     *     summary="Sync products from Stripe",
     *     tags={"Products"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="direction", type="string", enum={"from", "to"}, default="from")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sync completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="synced", type="object",
     *                 @OA\Property(property="products", type="integer"),
     *                 @OA\Property(property="prices", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=500, description="Sync failed")
     * )
     */
    public function sync(Request $request): JsonResponse
    {
        $direction = $request->input('direction', 'from');

        try {
            if ($direction === 'from') {
                $result = $this->productService->syncFromStripe();
            } else {
                $result = $this->productService->syncToStripe();
            }

            return response()->json([
                'message' => 'Sync completed successfully',
                'synced' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Sync failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}