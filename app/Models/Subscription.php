<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [

        'user_id',
        'plan_id',
        'plan_price_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'stripe_status',
        'trial_ends_at',
        'ends_at',
    ];


    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function price()
    {
        return $this->belongsTo(PlanPrice::class, 'plan_price_id');
    }
}
