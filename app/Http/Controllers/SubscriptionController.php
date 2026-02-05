<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Actions\CreateCheckoutSessionAction;
use App\Domains\Subscription\Actions\CancelSubscriptionAction;
use App\Domains\Subscription\Services\PlanService;
use App\Domains\Subscription\Services\SubscriptionManager;
use App\Domains\Shared\Services\StripeService;
use App\Exceptions\SubscriptionException;
use App\Http\Requests\Subscription\CreateCheckoutRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\PlanPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for subscription management endpoints
 * 
 * This controller provides a thin HTTP layer that coordinates
 * subscription operations through domain actions and services.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly CreateCheckoutSessionAction $createCheckout,
        private readonly CancelSubscriptionAction $cancelSubscription,
        private readonly PlanService $planService,
        private readonly SubscriptionManager $subscriptionManager,
        private readonly StripeService $stripeService
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
        $plans = $this->planService->getAllPlansWithPrices();

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
        $subscription = $this->subscriptionManager->getActiveSubscription($request->user());

        if (!$subscription) {
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
        $subscription = $this->subscriptionManager->getActiveSubscription($request->user());

        if (!$subscription) {
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

    /**
     * @OA\Post(
     *     path="/api/subscriptions/resume",
     *     summary="Resume a cancelled subscription",
     *     tags={"Subscriptions"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Subscription resumed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Subscription resumed successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Subscription")
     *         )
     *     ),
     *     @OA\Response(response=404, description="No subscription found"),
     *     @OA\Response(response=400, description="Subscription cannot be resumed")
     * )
     */
    public function resume(Request $request): JsonResponse
    {
        $subscription = $this->subscriptionManager->getActiveSubscription($request->user());

        if (!$subscription) {
            throw SubscriptionException::notFound();
        }

        if (!$subscription->ends_at) {
            return response()->json([
                'message' => 'Subscription is not scheduled for cancellation',
            ], 400);
        }

        try {
            // Resume in Stripe
            $this->stripeService->resumeSubscription($subscription->stripe_subscription_id);

            // Update local
            $subscription->update(['ends_at' => null]);

            return response()->json([
                'message' => 'Subscription resumed successfully',
                'data' => new SubscriptionResource($subscription->fresh(['plan', 'price'])),
            ]);

        } catch (\Exception $e) {
            Log::error('Error resuming subscription', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'message' => 'Failed to resume subscription',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/subscriptions/change-plan",
     *     summary="Change subscription plan/price",
     *     tags={"Subscriptions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"price_id"},
     *             @OA\Property(property="price_id", type="string", example="price_1234567890", description="New Stripe price ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Plan changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/Subscription")
     *         )
     *     ),
     *     @OA\Response(response=404, description="No subscription found"),
     *     @OA\Response(response=422, description="Invalid price ID")
     * )
     */
    public function changePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'price_id' => 'required|string',
        ]);

        $subscription = $this->subscriptionManager->getActiveSubscription($request->user());

        if (!$subscription) {
            throw SubscriptionException::notFound();
        }

        // Find the new price
        $newPrice = PlanPrice::where('stripe_price_id', $validated['price_id'])->first();

        if (!$newPrice) {
            return response()->json([
                'message' => 'Invalid price ID',
            ], 422);
        }

        try {
            // Change plan in Stripe
            $this->stripeService->changeSubscriptionPrice(
                $subscription->stripe_subscription_id,
                $validated['price_id']
            );

            // Update local subscription
            $subscription->update([
                'plan_id' => $newPrice->plan_id,
                'plan_price_id' => $newPrice->id,
            ]);

            return response()->json([
                'message' => 'Plan changed successfully',
                'data' => new SubscriptionResource($subscription->fresh(['plan', 'price'])),
            ]);

        } catch (\Exception $e) {
            Log::error('Error changing plan', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'message' => 'Failed to change plan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/subscriptions/details",
     *     summary="Get detailed subscription info from Stripe",
     *     tags={"Subscriptions"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Subscription details from Stripe",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="current_period_start", type="integer"),
     *                 @OA\Property(property="current_period_end", type="integer"),
     *                 @OA\Property(property="cancel_at_period_end", type="boolean"),
     *                 @OA\Property(property="canceled_at", type="integer", nullable=true),
     *                 @OA\Property(property="trial_end", type="integer", nullable=true),
     *                 @OA\Property(property="plan", type="object"),
     *                 @OA\Property(property="default_payment_method", type="object", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="No subscription found")
     * )
     */
    public function details(Request $request): JsonResponse
    {
        $subscription = $this->subscriptionManager->getActiveSubscription($request->user());

        if (!$subscription || !$subscription->stripe_subscription_id) {
            return response()->json([
                'data' => null,
            ]);
        }

        try {
            $stripeSubscription = $this->stripeService->retrieveSubscription(
                $subscription->stripe_subscription_id
            );

            return response()->json([
                'data' => [
                    'id' => $stripeSubscription->id,
                    'status' => $stripeSubscription->status,
                    'current_period_start' => $stripeSubscription->current_period_start,
                    'current_period_end' => $stripeSubscription->current_period_end,
                    'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end,
                    'canceled_at' => $stripeSubscription->canceled_at,
                    'trial_end' => $stripeSubscription->trial_end,
                    'plan' => [
                        'id' => $stripeSubscription->items->data[0]->price->id,
                        'amount' => $stripeSubscription->items->data[0]->price->unit_amount,
                        'currency' => $stripeSubscription->items->data[0]->price->currency,
                        'interval' => $stripeSubscription->items->data[0]->price->recurring->interval,
                    ],
                    'default_payment_method' => $stripeSubscription->default_payment_method ? [
                        'id' => $stripeSubscription->default_payment_method->id,
                        'brand' => $stripeSubscription->default_payment_method->card->brand ?? null,
                        'last4' => $stripeSubscription->default_payment_method->card->last4 ?? null,
                        'exp_month' => $stripeSubscription->default_payment_method->card->exp_month ?? null,
                        'exp_year' => $stripeSubscription->default_payment_method->card->exp_year ?? null,
                    ] : null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching subscription details', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'message' => 'Failed to fetch subscription details',
            ], 500);
        }
    }
}
