<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Services\StripeService;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{

    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    public function listPlans()
    {
        return Plan::all();
    }

    public function createCheckoutSession(Request $request)
    {

        $request->validate([
            'stripe_price_id' => 'required|string',
        ]);

        $stripePriceId = $request->stripe_price_id;

        if ($request->user()->hasActiveSubscription($stripePriceId)) {
            return response()->json(['message' => 'You already have an active subscription for this plan.'], 400);
        }

        $price = PlanPrice::where('stripe_price_id', $stripePriceId)->first();

        try {
            $session = $this->stripeService->createCheckoutSession(
                $request->user()->id,
                $price->stripe_price_id,
                config('app.url') . '/api/subscription/success?session_id={CHECKOUT_SESSION_ID}',
                config('app.url') . '/api/subscription/cancel'
            );

            return response()->json([
                'checkout_url' => $session->url,
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating Stripe Checkout Session: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create checkout session'], 500);
        }
    }

    public function status(Request $request)
    {
        $subscription = Subscription::where('user_id', $request->user()->id)->first();
        return response()->json($subscription);
    }


    public function cancelSubscription(Request $request)
    {
        $subscription = $request->user()->subscription;

        if (!$subscription || $subscription->stripe_status !== 'active') {
            return response()->json(['message' => 'No ffactive subscription found'], 404);
        }

        try {

            $this->stripeService->cancelSubscription($subscription);
            return response()->json(['message' => 'Subscription set to cancel at end of period.']);
        } catch (\Exception $e) {
            Log::error('Error cancelling subscription: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to cancel subscription'], 500);
        }
    }
}
