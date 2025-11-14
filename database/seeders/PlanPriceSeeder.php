<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanPriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // 1. Create Plans
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Basic subscription plan',
                'stripe_product_id' => 'prod_TQDHtRRM99Ei9Z',
                'prices' => [
                    [
                        'interval' => 'monthly',
                        'price' => 2000, // $20
                        'currency' => 'usd',
                        'stripe_price_id' => 'price_1STMrGHQSIupoCydZKpi5Z2l',
                    ],
                    [
                        'interval' => 'yearly',
                        'price' => 20000, // $200
                        'currency' => 'usd',
                        'stripe_price_id' => 'price_1STMrGHQSIupoCydlWBMZam4',
                    ],
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Pro plan with premium features',
                'stripe_product_id' => 'prod_TQDKhSXR5kiICN',
                'prices' => [
                    [
                        'interval' => 'monthly',
                        'price' => 3000, // $30
                        'currency' => 'usd',
                        'stripe_price_id' => 'price_1STMtkHQSIupoCydA2dTOSke',
                    ],
                    [
                        'interval' => 'yearly',
                        'price' => 32000, // $320
                        'currency' => 'usd',
                        'stripe_price_id' => 'price_1STMtkHQSIupoCydpCBFl8D2',
                    ],
                ],
            ],
        ];

        // 2. Insert into DB
        foreach ($plans as $planData) {
            $plan = Plan::create([
                'name' => $planData['name'],
                'slug' => $planData['slug'],
                'description' => $planData['description'],
                'stripe_product_id' => $planData['stripe_product_id'],
            ]);

            foreach ($planData['prices'] as $priceData) {
                PlanPrice::create([
                    'plan_id'        => $plan->id,
                    'stripe_price_id' => $priceData['stripe_price_id'],
                    'interval' => $priceData['interval'],
                    'price'         => $priceData['price'],
                    'currency'       => $priceData['currency'],
                ]);
            }
        }
    }
}
