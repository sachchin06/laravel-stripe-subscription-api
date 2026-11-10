<?php

namespace App\Enums;

/**
 * Stripe subscription statuses, stored in subscriptions.stripe_status.
 */
enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case TRIALING = 'trialing';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';
    case UNPAID = 'unpaid';
    case INCOMPLETE = 'incomplete';
    case INCOMPLETE_EXPIRED = 'incomplete_expired';
    case PAUSED = 'paused';

    public function isActive(): bool
    {
        return in_array($this, self::activeStatuses(), true);
    }

    /**
     * Statuses that give the user access to their plan.
     *
     * @return array<int, self>
     */
    public static function activeStatuses(): array
    {
        return [self::ACTIVE, self::TRIALING];
    }

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::TRIALING => 'Trial',
            self::PAST_DUE => 'Past Due',
            self::CANCELED => 'Canceled',
            self::UNPAID => 'Unpaid',
            self::INCOMPLETE => 'Incomplete',
            self::INCOMPLETE_EXPIRED => 'Incomplete Expired',
            self::PAUSED => 'Paused',
        };
    }
}
