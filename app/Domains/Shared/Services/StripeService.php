<?php

namespace App\Domains\Shared\Services;

use Stripe\StripeClient;

/**
 * Centralized Stripe service for all Stripe API interactions
 * 
 * This service provides a single point of access to Stripe functionality
 * and ensures consistent configuration across the application.
 */
class StripeService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('stripe.secret'));
    }

    /**
     * Get the Stripe client instance
     */
    public function client(): StripeClient
    {
        return $this->stripe;
    }

    // ==================== PRODUCTS ====================

    /**
     * Create a product in Stripe
     */
    public function createProduct(array $params): \Stripe\Product
    {
        return $this->stripe->products->create($params);
    }

    /**
     * Retrieve a product from Stripe
     */
    public function retrieveProduct(string $productId): \Stripe\Product
    {
        return $this->stripe->products->retrieve($productId);
    }

    /**
     * Update a product in Stripe
     */
    public function updateProduct(string $productId, array $params): \Stripe\Product
    {
        return $this->stripe->products->update($productId, $params);
    }

    /**
     * List all products from Stripe
     */
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

    /**
     * Create a price in Stripe
     */
    public function createPrice(array $params): \Stripe\Price
    {
        return $this->stripe->prices->create($params);
    }

    /**
     * Retrieve a price from Stripe
     */
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

    /**
     * List all prices from Stripe
     */
    public function listPrices(array $params = []): \Stripe\Collection
    {
        return $this->stripe->prices->all($params);
    }

    /**
     * List prices for a specific product
     */
    public function listPricesForProduct(string $productId): \Stripe\Collection
    {
        return $this->stripe->prices->all(['product' => $productId, 'active' => true]);
    }

    /**
     * Archive (deactivate) a price in Stripe
     */
    public function archivePrice(string $priceId): \Stripe\Price
    {
        return $this->stripe->prices->update($priceId, ['active' => false]);
    }

    // ==================== CUSTOMERS ====================

    /**
     * Create a customer
     */
    public function createCustomer(array $params): \Stripe\Customer
    {
        return $this->stripe->customers->create($params);
    }

    /**
     * Retrieve a customer
     */
    public function retrieveCustomer(string $customerId): \Stripe\Customer
    {
        return $this->stripe->customers->retrieve($customerId);
    }

    /**
     * Update a customer
     */
    public function updateCustomer(string $customerId, array $params): \Stripe\Customer
    {
        return $this->stripe->customers->update($customerId, $params);
    }

    /**
     * Delete a customer
     */
    public function deleteCustomer(string $customerId): \Stripe\Customer
    {
        return $this->stripe->customers->delete($customerId);
    }

    // ==================== CHECKOUT ====================

    /**
     * Create a checkout session
     */
    public function createCheckoutSession(array $params): \Stripe\Checkout\Session
    {
        return $this->stripe->checkout->sessions->create($params);
    }

    /**
     * Retrieve a checkout session
     */
    public function retrieveCheckoutSession(string $sessionId): \Stripe\Checkout\Session
    {
        return $this->stripe->checkout->sessions->retrieve($sessionId);
    }

    // ==================== SUBSCRIPTIONS ====================

    /**
     * Create a subscription
     */
    public function createSubscription(array $params): \Stripe\Subscription
    {
        return $this->stripe->subscriptions->create($params);
    }

    /**
     * Retrieve a subscription
     */
    public function retrieveSubscription(string $subscriptionId): \Stripe\Subscription
    {
        return $this->stripe->subscriptions->retrieve($subscriptionId, [
            'expand' => ['latest_invoice', 'default_payment_method']
        ]);
    }

    /**
     * Update a subscription
     */
    public function updateSubscription(string $subscriptionId, array $params): \Stripe\Subscription
    {
        return $this->stripe->subscriptions->update($subscriptionId, $params);
    }

    /**
     * Cancel a subscription immediately
     */
    public function cancelSubscription(string $subscriptionId): \Stripe\Subscription
    {
        return $this->stripe->subscriptions->cancel($subscriptionId);
    }

    /**
     * Cancel a subscription at period end
     */
    public function cancelSubscriptionAtPeriodEnd(string $subscriptionId): \Stripe\Subscription
    {
        return $this->stripe->subscriptions->update($subscriptionId, [
            'cancel_at_period_end' => true
        ]);
    }

    /**
     * Resume a subscription that was set to cancel at period end
     */
    public function resumeSubscription(string $subscriptionId): \Stripe\Subscription
    {
        return $this->stripe->subscriptions->update($subscriptionId, [
            'cancel_at_period_end' => false
        ]);
    }

    /**
     * Change subscription plan/price
     */
    public function changeSubscriptionPrice(string $subscriptionId, string $newPriceId): \Stripe\Subscription
    {
        $subscription = $this->retrieveSubscription($subscriptionId);
        $itemId = $subscription->items->data[0]->id;

        return $this->stripe->subscriptions->update($subscriptionId, [
            'items' => [
                [
                    'id' => $itemId,
                    'price' => $newPriceId,
                ]
            ],
            'proration_behavior' => 'create_prorations',
        ]);
    }

    /**
     * List subscriptions for a customer
     */
    public function listSubscriptions(string $customerId): \Stripe\Collection
    {
        return $this->stripe->subscriptions->all(['customer' => $customerId]);
    }

    // ==================== INVOICES ====================

    /**
     * List invoices for a customer
     */
    public function listInvoices(string $customerId, int $limit = 10): \Stripe\Collection
    {
        return $this->stripe->invoices->all([
            'customer' => $customerId,
            'limit' => $limit,
        ]);
    }

    /**
     * Retrieve upcoming invoice
     */
    public function retrieveUpcomingInvoice(string $customerId): ?\Stripe\Invoice
    {
        try {
            return $this->stripe->invoices->upcoming(['customer' => $customerId]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return null;
        }
    }

    /**
     * Retrieve a specific invoice
     */
    public function retrieveInvoice(string $invoiceId): \Stripe\Invoice
    {
        return $this->stripe->invoices->retrieve($invoiceId);
    }

    // ==================== BILLING PORTAL ====================

    /**
     * Create a billing portal session
     */
    public function createBillingPortalSession(string $customerId, string $returnUrl): \Stripe\BillingPortal\Session
    {
        return $this->stripe->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);
    }

    // ==================== WEBHOOKS ====================

    /**
     * Construct webhook event from payload and signature
     */
    public function constructWebhookEvent(string $payload, string $signature): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent(
            $payload,
            $signature,
            config('stripe.webhook.secret')
        );
    }

    // ==================== PAYMENT METHODS ====================

    /**
     * List payment methods for a customer
     */
    public function listPaymentMethods(string $customerId, string $type = 'card'): \Stripe\Collection
    {
        return $this->stripe->paymentMethods->all([
            'customer' => $customerId,
            'type' => $type,
        ]);
    }

    /**
     * Detach a payment method from customer
     */
    public function detachPaymentMethod(string $paymentMethodId): \Stripe\PaymentMethod
    {
        return $this->stripe->paymentMethods->detach($paymentMethodId);
    }
}