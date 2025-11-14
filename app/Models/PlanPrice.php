<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanPrice extends Model
{
    protected $fillable = [
        'plan_id',
        'stripe_price_id',
        'price',
        'currency',
        'interval',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
