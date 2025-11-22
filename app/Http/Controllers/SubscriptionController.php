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

    public function CreateCheckout(Request $request)
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
        #validate request
        $request->validate([
            'price_id' => 'required|integer',
        ]);

        $subscription = Subscription::where('user_id', $request->user()->id)
            ->where('plan_price_id', $request->price_id)
            ->where('stripe_status', 'active')
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'No subscription found'], 404);
        }

        Stripe::setApiKey(config('services.stripe.secret'));
        $stripeSub = \Stripe\Subscription::retrieve($subscription->stripe_subscription_id);
        Log::info('Retrieved Stripe Subscription: ' . json_encode($stripeSub));
        $stripeSub->cancel();

        #need to correct: make sure only send when webhook confirms cancellation

        return response()->json(['message' => 'Subscription canceled']);
    }
}
