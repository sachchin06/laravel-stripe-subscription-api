<?php

namespace App\Enums;

enum BillingInterval: string
{
    case MONTH = 'month';
    case YEAR = 'year';

    public function label(): string
    {
        return match ($this) {
            self::MONTH => 'Monthly',
            self::YEAR => 'Yearly',
        };
    }
}
