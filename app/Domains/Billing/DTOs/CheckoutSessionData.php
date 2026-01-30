<?php

namespace App\Domains\Billing\DTOs;

use App\Domains\Shared\DTOs\BaseDTO;

/**
 * Data transfer object for checkout session creation
 */
readonly class CheckoutSessionData extends BaseDTO
{
    public function __construct(
        public int $userId,
        public string $stripePriceId,
        public string $customerEmail,
        public string $successUrl,
        public string $cancelUrl,
        public ?string $stripeCustomerId = null,
        public ?int $trialPeriodDays = null,
        public array $metadata = []
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'stripe_price_id' => $this->stripePriceId,
            'customer_email' => $this->customerEmail,
            'success_url' => $this->successUrl,
            'cancel_url' => $this->cancelUrl,
            'stripe_customer_id' => $this->stripeCustomerId,
            'trial_period_days' => $this->trialPeriodDays,
            'metadata' => $this->metadata,
        ];
    }
}