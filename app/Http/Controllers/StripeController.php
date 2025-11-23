<?php

namespace App\Http\Controllers;

use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;

class StripeController extends Controller
{

    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $secret
            );
        } catch (\Exception $e) {
            return response('Webhook error: ' . $e->getMessage(), 400);
        }

        $type = $event->type;
        $data = $event->data->object;

        switch ($type) {

            case 'checkout.session.completed':
                Log::info('checkout.session.completed Session data: ' . json_encode($data));
                $this->createSubscriptionFromSession($data);
                break;

            case 'customer.subscription.updated':
                $this->updateSubscriptionStatus($data);
                Log::info('customer.subscription.updated Session data: ' . json_encode($data));
                break;

            case 'customer.subscription.deleted':
                $this->updateSubscriptionStatus($data);
                Log::info('customer.subscription.deleted Session data: ' . json_encode($data));
                break;

            default:
                Log::info("Unhandled Stripe event type: {$type}");
        }

        return response('Webhook handled', 200);
    }

    protected function createSubscriptionFromSession($session)
    {
        try {
            $userId = $session->client_reference_id;
            $stripeSubId = $session->subscription;
            $stripeCustomerId = $session->customer;

            try {
                $stripeSubscription = $this->stripeService->getSubscription($stripeSubId);
            } catch (\Exception $e) {
                Log::error("Failed to fetch subscription from Stripe: " . $e->getMessage());
                throw $e;
            }

            $priceId = $stripeSubscription->items->data[0]->price->id;

            $price = PlanPrice::where('stripe_price_id', $priceId)->first();
            $plan = $price->plan;

            $obj = [
                'user_id' => $userId,
                'plan_id' => $plan->id,
                'plan_price_id' => $price->id,
                'stripe_subscription_id' => $stripeSubId,
                'stripe_customer_id' => $stripeCustomerId,
                'stripe_status' => $stripeSubscription->status,
                'ends_at' => null,
            ];

            Subscription::create($obj);

            Log::info('object created successfully');
        } catch (\Exception $e) {
            Log::error("Error in createSubscriptionFromSession: " . $e->getMessage(), [
                'session_id' => $session->id ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function updateSubscriptionStatus($stripeObject)
    {

        try {
            $stripeSubId = $stripeObject->id ?? null;
            Log::info('Updating subscription with Stripe ID: ' . $stripeSubId);
            $stripeCustomerId = $stripeObject->customer ?? null;

            if (!isset($stripeSubId) || !isset($stripeCustomerId)) {
                throw new \InvalidArgumentException("Invalid Stripe Object: Missing 'id' or 'customer' field.");
            }

            $subscription = Subscription::where('stripe_subscription_id', $stripeSubId)
                ->where('stripe_customer_id', $stripeCustomerId)
                ->first();

            if (!$subscription) {
                Log::warning("Subscription record not found for Stripe ID {$stripeSubId}");
                return;
            }

            $subscription->stripe_status = $stripeObject->status;

            if (isset($stripeObject->cancel_at) && $stripeObject->cancel_at) {
                $subscription->ends_at = \Carbon\Carbon::createFromTimestamp($stripeObject->cancel_at);
            }
            $subscription->save();
            Log::info('Subscription status updated to ' . $stripeObject->status);
        } catch (\Exception $e) {
            Log::error("Failed to update subscription status: " . $e->getMessage(), [
                'stripe_id' => $stripeSubId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
