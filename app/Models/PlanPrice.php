<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPrice extends Model
{
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
            'price' => 'decimal:2',
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
        return number_format($this->price, 2) . ' ' . strtoupper($this->currency);
    }

    public function isMonthly(): bool
    {
        return $this->interval === 'month';
    }

    public function isYearly(): bool
    {
        return $this->interval === 'year';
    }
}
