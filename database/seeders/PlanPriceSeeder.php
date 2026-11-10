<?php

namespace Database\Seeders;

use App\Enums\BillingInterval;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Creates the Basic and Pro plans locally, without Stripe IDs.
 *
 * Run `php artisan stripe:sync to` afterwards to create the matching
 * products and prices in your own Stripe account and store their IDs.
 */
class PlanPriceSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Basic subscription plan',
                'prices' => [
                    BillingInterval::MONTH->value => 2000, // $20.00
                    BillingInterval::YEAR->value => 20000, // $200.00
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Pro plan with premium features',
                'prices' => [
                    BillingInterval::MONTH->value => 3000, // $30.00
                    BillingInterval::YEAR->value => 32000, // $320.00
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $plan = Plan::firstOrCreate(
                ['slug' => $planData['slug']],
                ['name' => $planData['name'], 'description' => $planData['description']]
            );

            foreach ($planData['prices'] as $interval => $amount) {
                $plan->prices()->firstOrCreate(
                    ['interval' => $interval],
                    ['price' => $amount, 'currency' => config('stripe.currency', 'usd')]
                );
            }
        }
    }
}
