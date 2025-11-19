<?php

namespace App\Http\Controllers;

use App\Models\PlanPrice;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;

class StripeController extends Controller
{
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

        switch ($event->type) {

            case 'checkout.session.completed':
                Log::info('checkout.session.completed event received');

                $session = $event->data->object;
                Log::info('checkout.session.completed Session data: ' . json_encode($session));
                $this->createSubscriptionFromSession($session);
                break;

            case 'customer.subscription.deleted':
                $session = $event->data->object;
                $this->updateSubscriptionStatus($session, 'cancelled');
                Log::info('customer.subscription.deleted Session data: ' . json_encode($session));
                break;

            case 'invoice.payment_succeeded':
                $session = $event->data->object;
                Log::info('invoice.payment_succeeded Session data: ' . json_encode($session));
                break;
        }

        return response('Webhook handled', 200);
    }

    protected function createSubscriptionFromSession($session)
    {
        $userId = $session->client_reference_id;
        $stripeSubId = $session->subscription;
        $stripeCustomerId = $session->customer;

        Stripe::setApiKey(config('services.stripe.secret'));
        $stripeSubscription = \Stripe\Subscription::retrieve($stripeSubId);

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
    }

    protected function updateSubscriptionStatus($session, $status)
    {
        $stripeSubId = $session->id;
        Log::info('Updating subscription with Stripe ID: ' . $stripeSubId);
        $stripeCustomerId = $session->customer;
        LOG::info('Stripe Customer ID: ' . $stripeCustomerId);

        $subscription = Subscription::where('stripe_subscription_id', $stripeSubId)
            ->where('stripe_customer_id', $stripeCustomerId)
            ->first();

        if ($subscription) {
            $subscription->stripe_status = $status;
            $subscription->save();
            Log::info('Subscription status updated to ' . $status);
        } else {
            Log::warning('Subscription with Stripe ID ' . $stripeSubId . ' not found.');
        }
    }
}
