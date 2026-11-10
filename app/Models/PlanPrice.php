<?php

namespace App\Models;

use App\Enums\BillingInterval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'stripe_price_id',
        'price',
        'currency',
        'interval',
    ];

    protected function casts(): array
    {
        return [
            // Smallest currency unit (e.g. cents), the same as Stripe's unit_amount.
            'price' => 'integer',
            'interval' => BillingInterval::class,
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_price_id');
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price / 100, 2).' '.strtoupper($this->currency);
    }

    public function isMonthly(): bool
    {
        return $this->interval === BillingInterval::MONTH;
    }

    public function isYearly(): bool
    {
        return $this->interval === BillingInterval::YEAR;
    }
}
