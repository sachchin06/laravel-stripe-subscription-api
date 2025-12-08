<?php

namespace App\Http\Controllers;

use App\Actions\Subscription\CancelSubscriptionAction;
use App\Actions\Subscription\CreateCheckoutSessionAction;
use App\Exceptions\SubscriptionException;
use App\Http\Requests\Subscription\CreateCheckoutRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly CreateCheckoutSessionAction $createCheckout,
        private readonly CancelSubscriptionAction $cancelSubscription
    ) {}

    /**
     * @OA\Get(
     *     path="/api/plans",
     *     summary="Get all subscription plans",
     *     tags={"Plans"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of subscription plans",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Plan")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function listPlans(): JsonResponse
    {
        Log::info('hii');
        $plans = Plan::with('prices')->get();

        return response()->json([
            'data' => PlanResource::collection($plans),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/subscriptions/checkout",
     *     summary="Create Stripe checkout session",
     *     tags={"Subscriptions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"price_id"},
     *             @OA\Property(property="price_id", type="string", example="price_1234567890", description="Stripe price ID from the plans list")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Checkout session created",
     *         @OA\JsonContent(
     *             @OA\Property(property="checkout_url", type="string", example="https://checkout.stripe.com/c/pay/cs_test_...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Already subscribed or invalid price",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You already have an active subscription for this plan.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function createCheckoutSession(CreateCheckoutRequest $request): JsonResponse
    {
        try {
            $checkoutUrl = $this->createCheckout->execute(
                user: $request->user(),
                stripePriceId: $request->validated('price_id')
            );

            return response()->json([
                'checkout_url' => $checkoutUrl,
            ]);
        } catch (SubscriptionException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error creating Stripe Checkout Session', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Failed to create checkout session',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/subscriptions",
     *     summary="Get user's subscription status",
     *     tags={"Subscriptions"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Subscription details",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 oneOf={
     *                     @OA\Schema(ref="#/components/schemas/Subscription"),
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
    public function status(Request $request): JsonResponse
    {
        $subscription = $request->user()->subscription()->with(['plan', 'price'])->first();

        if (! $subscription) {
            return response()->json([
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => new SubscriptionResource($subscription),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/subscriptions/cancel",
     *     summary="Cancel user's subscription",
     *     tags={"Subscriptions"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Subscription cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Subscription will be cancelled at the end of the billing period")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No subscription found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No active subscription found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function cancel(Request $request): JsonResponse
    {
        $subscription = $request->user()->subscription;

        if (! $subscription) {
            throw SubscriptionException::notFound();
        }

        try {
            $this->cancelSubscription->execute($subscription);

            return response()->json([
                'message' => 'Subscription will be cancelled at the end of the billing period',
            ]);
        } catch (SubscriptionException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error cancelling subscription', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'message' => 'Failed to cancel subscription',
            ], 500);
        }
    }
}
