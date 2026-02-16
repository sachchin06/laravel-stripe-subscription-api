<?php

namespace App\Domains\Billing\Services;

use App\Domains\Shared\Services\StripeService;
use App\Models\User;

/**
 * Service for managing invoices and billing portal
 */
class InvoiceService
{
    public function __construct(
        private readonly StripeService $stripeService
    ) {}

    /**
     * Get user's invoice history from Stripe
     */
    public function getInvoiceHistory(User $user, int $limit = 10): array
    {
        if (!$user->stripe_customer_id) {
            return [];
        }

        try {
            $invoices = $this->stripeService->client()->invoices->all([
                'customer' => $user->stripe_customer_id,
                'limit' => $limit,
            ]);

            return array_map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'amount_paid' => $invoice->amount_paid,
                    'amount_due' => $invoice->amount_due,
                    'currency' => strtoupper($invoice->currency),
                    'status' => $invoice->status,
                    'created' => $invoice->created,
                    'due_date' => $invoice->due_date,
                    'hosted_invoice_url' => $invoice->hosted_invoice_url,
                    'invoice_pdf' => $invoice->invoice_pdf,
                    'description' => $invoice->lines->data[0]->description ?? null,
                ];
            }, $invoices->data);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch invoice history', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Create billing portal session for customer self-service
     */
    public function createBillingPortalSession(User $user, string $returnUrl): ?string
    {
        if (!$user->stripe_customer_id) {
            return null;
        }

        try {
            $session = $this->stripeService->client()->billingPortal->sessions->create([
                'customer' => $user->stripe_customer_id,
                'return_url' => $returnUrl,
            ]);

            return $session->url;

        } catch (\Exception $e) {
            \Log::error('Failed to create billing portal session', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get upcoming invoice for customer
     */
    public function getUpcomingInvoice(User $user): ?array
    {
        if (!$user->stripe_customer_id) {
            return null;
        }

        try {
            $invoice = $this->stripeService->client()->invoices->upcoming([
                'customer' => $user->stripe_customer_id,
            ]);

            return [
                'amount_due' => $invoice->amount_due,
                'currency' => strtoupper($invoice->currency),
                'period_start' => $invoice->period_start,
                'period_end' => $invoice->period_end,
                'next_payment_attempt' => $invoice->next_payment_attempt,
                'description' => $invoice->lines->data[0]->description ?? null,
            ];

        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // No upcoming invoice
            return null;
        } catch (\Exception $e) {
            \Log::error('Failed to fetch upcoming invoice', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}