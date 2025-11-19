<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function listPlans()
    {
        return Plan::all();
    }


    public function createCheckout(Request $request)
    {

        $request->validate([
            'price_id' => 'required|integer',
        ]);

        #check if user already has an active subscription
        $existingSubscription = Subscription::where('user_id', $request->user()->id)
            ->where('plan_price_id', $request->price_id)
            ->where('stripe_status', 'active')
            ->first();
        if ($existingSubscription) {
            return response()->json(['message' => 'You already have an active subscription for this plan.'], 400);
        }

        $price = PlanPrice::with('plan')->findOrFail($request->price_id);

        Stripe::setApiKey(config('services.stripe.secret'));

        $session = Session::create([
            'mode' => 'subscription',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $price->stripe_price_id,
                'quantity' => 1,
            ]],
            'client_reference_id' => $request->user()->id,
            'success_url' => config('app.url') . '/api/subscription/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.url') . '/api/subscription/cancel',
        ]);

        return response()->json([
            'checkout_url' => $session->url,
        ]);
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

        // $subscription->update(['stripe_status' => 'canceled']);

        return response()->json(['message' => 'Subscription canceled']);
    }
}
