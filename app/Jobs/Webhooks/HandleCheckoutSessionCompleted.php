<?php

namespace App\Jobs\Webhooks;

use App\Actions\Subscription\CreateSubscriptionAction;
use App\DataTransferObjects\SubscriptionData;
use App\Models\PlanPrice;
use App\Services\Stripe\StripeSubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;

class HandleCheckoutSessionCompleted implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly array $sessionData
    ) {}

    public function handle(
        StripeSubscriptionService $subscriptionService,
        CreateSubscriptionAction $createSubscription
    ): void {
        try {
            Log::info('HandleCheckoutSessionCompleted job started', [
                'session_id' => $this->sessionData['id'] ?? 'unknown',
            ]);
            
            $session = Session::constructFrom($this->sessionData);

            // Validate required fields
            if (!$session->subscription) {
                Log::warning('Checkout session has no subscription ID', [
                    'session_id' => $session->id,
                    'mode' => $session->mode,
                ]);
                return;
            }

            if (!$session->client_reference_id) {
                Log::error('Checkout session missing client_reference_id', [
                    'session_id' => $session->id,
                ]);
                return;
            }

            $userId = (int) $session->client_reference_id;
            $stripeSubId = $session->subscription;
            $stripeCustomerId = $session->customer;

            Log::info('Processing checkout session', [
                'user_id' => $userId,
                'stripe_subscription_id' => $stripeSubId,
                'stripe_customer_id' => $stripeCustomerId,
            ]);

            // Fetch full subscription details from Stripe
            $stripeSubscription = $subscriptionService->retrieve($stripeSubId);

            // Get the price ID from the subscription
            $priceId = $stripeSubscription->items->data[0]->price->id;

            // Find the plan price in our database
            $price = PlanPrice::where('stripe_price_id', $priceId)->firstOrFail();

            // Create subscription data
            $subscriptionData = new SubscriptionData(
                userId: $userId,
                planId: $price->plan_id,
                planPriceId: $price->id,
                stripeSubscriptionId: $stripeSubId,
                stripeCustomerId: $stripeCustomerId,
                stripeStatus: $stripeSubscription->status,
            );

            // Create the subscription
            $createSubscription->execute($subscriptionData);

            Log::info('Subscription created successfully', [
                'user_id' => $userId,
                'stripe_subscription_id' => $stripeSubId,
                'plan_id' => $price->plan_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to handle checkout.session.completed', [
                'error' => $e->getMessage(),
                'session_id' => $this->sessionData['id'] ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
