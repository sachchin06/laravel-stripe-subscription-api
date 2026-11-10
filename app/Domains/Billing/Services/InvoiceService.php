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

    public function getInvoiceHistory(User $user, int $limit = 10): array
    {
        if (!$user->stripe_customer_id) {
            return [];
        }

        $invoices = $this->stripeService->listInvoices($user->stripe_customer_id, $limit);

        return array_map(fn ($invoice) => [
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
        ], $invoices->data);
    }

    public function createBillingPortalSession(User $user, string $returnUrl): ?string
    {
        if (!$user->stripe_customer_id) {
            return null;
        }

        return $this->stripeService
            ->createBillingPortalSession($user->stripe_customer_id, $returnUrl)
            ->url;
    }

    public function getUpcomingInvoice(User $user): ?array
    {
        if (!$user->stripe_customer_id) {
            return null;
        }

        $invoice = $this->stripeService->previewUpcomingInvoice(
            $user->stripe_customer_id,
            $user->activeSubscription()?->stripe_subscription_id
        );

        if (!$invoice) {
            return null;
        }

        return [
            'amount_due' => $invoice->amount_due,
            'currency' => strtoupper($invoice->currency),
            'period_start' => $invoice->period_start,
            'period_end' => $invoice->period_end,
            'next_payment_attempt' => $invoice->next_payment_attempt,
            'description' => $invoice->lines->data[0]->description ?? null,
        ];
    }
}