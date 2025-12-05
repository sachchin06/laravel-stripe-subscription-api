<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'stripe_product_id',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function getMonthlyPrice(): ?PlanPrice
    {
        return $this->prices()->where('interval', 'month')->first();
    }

    public function getYearlyPrice(): ?PlanPrice
    {
        return $this->prices()->where('interval', 'year')->first();
    }
}
