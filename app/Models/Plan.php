<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'stripe_product_id',
    ];

    public function prices()
    {
        return $this->hasMany(PlanPrice::class);
    }
}
