<?php

namespace App\Domains\Shared\Services;

use Illuminate\Support\Str;
use Stripe\StripeClient;

/**
 * Centralised Stripe service for all Stripe API interactions
 *
 * This service provides a single point of access to Stripe functionality
 * and ensures consistent configuration across the application.
 */
class StripeService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient([
            'api_key' => config('stripe.secret'),
            'stripe_version' => config('stripe.api_version'),
            // Safe because every create call below sends an idempotency key.
            'max_network_retries' => 2,
        ]);
    }

    /**
     * Stripe stores the result of a request under its idempotency key for 24 hours,
     * so repeating a create call with the same key returns the original object
     * instead of creating a duplicate. Callers pass a key derived from local data
     * when a natural one exists; otherwise a random key still protects network retries.
     */
    private function idempotencyOptions(?string $key): array
    {
        return ['idempotency_key' => $key ?? (string) Str::uuid()];
    }

    public function client(): StripeClient
    {
        return $this->stripe;
    }

    // ==================== PRODUCTS ====================

    public function createProduct(array $params, ?string $idempotencyKey = null): \Stripe\Product
    {
        return $this->stripe->products->create($params, $this->idempotencyOptions($idempotencyKey));
    }

    public function retrieveProduct(string $productId): \Stripe\Product
    {
        return $this->stripe->products->retrieve($productId);
    }

    public function updateProduct(string $productId, array $params): \Stripe\Product
    {
        return $this->stripe->products->update($productId, $params);
    }

    public function listProducts(array $params = []): \Stripe\Collection
    {
        return $this->stripe->products->all($params);
    }

    /**
     * Archive (deactivate) a product in Stripe
     */
    public function archiveProduct(string $productId): \Stripe\Product
    {
        return $this->stripe->products->update($productId, ['active' => false]);
    }

    // ==================== PRICES ====================

    public function createPrice(array $params, ?string $idempotencyKey = null): \Stripe\Price
    {
        return $this->stripe->prices->create($params, $this->idempotencyOptions($idempotencyKey));
    }

    public function retrievePrice(string $priceId): \Stripe\Price
    {
        return $this->stripe->prices->retrieve($priceId);
    }

    /**
     * Update a price in Stripe (limited - can only update metadata, nickname, active)
     */
    public function updatePrice(string $priceId, array $params): \Stripe\Price
    {
        return $this->stripe->prices->update($priceId, $params);
    }

    public function listPrices(array $params = []): \Stripe\Collection
    {
        return $this->stripe->prices->all($params);
    }

    public function listPricesForProduct(string $productId): \Stripe\Collection
    {
        return $this->stripe->prices->all(['product' => $productId, 'active' => true, 'limit' => 100]);
    }

    /**
     * Archive (deactivate) a price in Stripe
     */
    public function archivePrice(string $priceId): \Stripe\Price
    {
        return $this->stripe->prices->update($priceId, ['active' => false]);
    }

    // ==================== CUSTOMERS ====================

    public function createCustomer(array $params, ?string $idempotencyKey = null): \Stripe\Customer
    {
        return $this->stripe->customers->create($params, $this->idempotencyOptions($idempotencyKey));
    }

    public function retrieveCustomer(string $customerId): \Stripe\Customer
    {
        return $this->stripe->customers->retrieve($customerId);
    }

    public function updateCustomer(string $customerId, array $params): \Stripe\Customer
    {
        return $this->stripe->customers->update($customerId, $params);
    }

    public function deleteCustomer(string $customerId): \Stripe\Customer
    {
        return $this->stripe->customers->delete($customerId);
    }

    // ==================== CHECKOUT ====================

    public function createCheckoutSession(array $params, ?string $idempotencyKey = null): \Stripe\Checkout\Session
    {
        return $this->stripe->checkout->sessions->create($params, $this->idempotencyOptions($idempotencyKey));
    }

    public function retrieveCheckoutSession(string $sessionId): \Stripe\Checkout\Session
    {
        return $this->stripe->checkout->sessions->retrieve($sessionId);
    }

    /**
     * Checkout sessions for this customer that can still be paid. Use
     * autoPagingIterator() on the result to read every page.
     */
    public function listOpenCheckoutSessions(string $customerId): \Stripe\Collection
    {
        return $this->stripe->checkout->sessions->all([
            'customer' => $customerId,
            'status' => 'open',
            'limit' => 100,
        ]);
    }

    public function expireCheckoutSession(string $sessionId): \Stripe\Checkout\Session
    {
        return $this->stripe->checkout->sessions->expire($sessionId);
    }

    // ==================== SUBSCRIPTIONS ====================

    public function createSubscription(array $params, ?string $idempotencyKey = null): \Stripe\Subscription
    {
        return $this->stripe->subscriptions->create($params, $this->idempotencyOptions($idempotencyKey));
    }

    public function retrieveSubscription(string $subscriptionId): \Stripe\Subscription
    {
        return $this->stripe->subscriptions->retrieve($subscriptionId, [
            'expand' => ['latest_invoice', 'default_payment_method'],
        ]);
    }

    public function updateSubscription(string $subscriptionId, array $params): \Stripe\Subscription
    {
        return $this->stripe->subscriptions->update($subscriptionId, $params);
    }

    public function cancelSubscription(string $subscriptionId): \Stripe\Subscription
    {
        return $this->stripe->subscriptions->cancel($subscriptionId);
    }

    public function cancelSubscriptionAtPeriodEnd(string $subscriptionId): \Stripe\Subscription
    {
        return $this->stripe->subscriptions->update($subscriptionId, [
            'cancel_at_period_end' => true,
        ]);
    }

    /**
     * Resume a subscription that was set to cancel at period end
     */
    public function resumeSubscription(string $subscriptionId): \Stripe\Subscription
    {
        return $this->stripe->subscriptions->update($subscriptionId, [
            'cancel_at_period_end' => false,
        ]);
    }

    public function changeSubscriptionPrice(string $subscriptionId, string $newPriceId): \Stripe\Subscription
    {
        $subscription = $this->retrieveSubscription($subscriptionId);
        $itemId = $subscription->items->data[0]->id;

        return $this->stripe->subscriptions->update($subscriptionId, [
            'items' => [
                [
                    'id' => $itemId,
                    'price' => $newPriceId,
                ],
            ],
            'proration_behavior' => 'create_prorations',
        ]);
    }

    public function listSubscriptions(string $customerId): \Stripe\Collection
    {
        return $this->stripe->subscriptions->all(['customer' => $customerId]);
    }

    // ==================== INVOICES ====================

    public function listInvoices(string $customerId, int $limit = 10): \Stripe\Collection
    {
        return $this->stripe->invoices->all([
            'customer' => $customerId,
            'limit' => $limit,
        ]);
    }

    /**
     * Preview the next invoice. The old invoices->upcoming() endpoint was
     * removed in the 2025 API versions; createPreview replaces it.
     * Returns null when Stripe has nothing to preview (no subscription).
     */
    public function previewUpcomingInvoice(string $customerId, ?string $subscriptionId = null): ?\Stripe\Invoice
    {
        $params = ['customer' => $customerId];

        if ($subscriptionId) {
            $params['subscription'] = $subscriptionId;
        }

        try {
            return $this->stripe->invoices->createPreview($params);
        } catch (\Stripe\Exception\InvalidRequestException) {
            return null;
        }
    }

    public function retrieveInvoice(string $invoiceId): \Stripe\Invoice
    {
        return $this->stripe->invoices->retrieve($invoiceId);
    }

    // ==================== BILLING PORTAL ====================

    public function createBillingPortalSession(string $customerId, string $returnUrl): \Stripe\BillingPortal\Session
    {
        return $this->stripe->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);
    }

    // ==================== WEBHOOKS ====================

    public function constructWebhookEvent(string $payload, string $signature): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent(
            $payload,
            $signature,
            config('stripe.webhook.secret')
        );
    }

    // ==================== PAYMENT METHODS ====================

    public function listPaymentMethods(string $customerId, string $type = 'card'): \Stripe\Collection
    {
        return $this->stripe->paymentMethods->all([
            'customer' => $customerId,
            'type' => $type,
        ]);
    }

    public function detachPaymentMethod(string $paymentMethodId): \Stripe\PaymentMethod
    {
        return $this->stripe->paymentMethods->detach($paymentMethodId);
    }
}
