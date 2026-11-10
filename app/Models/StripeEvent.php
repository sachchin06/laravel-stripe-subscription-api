<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Stripe webhook event that has been accepted for processing.
 */
class StripeEvent extends Model
{
    protected $fillable = [
        'event_id',
        'type',
    ];
}
