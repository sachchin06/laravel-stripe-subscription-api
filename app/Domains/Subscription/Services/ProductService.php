<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Shared\Services\StripeService;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for managing Stripe products and syncing with local database
 */
class ProductService
{
    public function __construct(
        private readonly StripeService $stripeService
    ) {}

    /**
     * Create a new plan with prices in both Stripe and local database
     */
    public function createPlanWithPrices(array $planData): Plan
    {
        return DB::transaction(function () use ($planData) {
            // Create product in Stripe
            $stripeProduct = $this->stripeService->createProduct([
                'name' => $planData['name'],
                'description' => $planData['description'] ?? null,
                'metadata' => [
                    'slug' => $planData['slug'] ?? Str::slug($planData['name']),
                ],
            ]);

            // Create local plan
            $plan = Plan::create([
                'name' => $planData['name'],
                'slug' => $planData['slug'] ?? Str::slug($planData['name']),
                'description' => $planData['description'] ?? null,
                'stripe_product_id' => $stripeProduct->id,
            ]);

            // Create prices if provided
            if (isset($planData['prices'])) {
                foreach ($planData['prices'] as $priceData) {
                    $this->createPriceForPlan($plan, $priceData);
                }
            }

            Log::info('Plan created with Stripe product', [
                'plan_id' => $plan->id,
                'stripe_product_id' => $stripeProduct->id,
            ]);

            return $plan->load('prices');
        });
    }

    /**
     * Create a price for an existing plan
     */
    public function createPriceForPlan(Plan $plan, array $priceData): PlanPrice
    {
        // Create price in Stripe
        $stripePrice = $this->stripeService->createPrice([
            'product' => $plan->stripe_product_id,
            'unit_amount' => $priceData['price'], // Price in cents
            'currency' => $priceData['currency'] ?? config('stripe.currency', 'usd'),
            'recurring' => [
                'interval' => $priceData['interval'] === 'yearly' ? 'year' : 'month',
                'interval_count' => 1,
            ],
            'metadata' => [
                'plan_id' => $plan->id,
                'interval' => $priceData['interval'],
            ],
        ]);

        // Create local price
        $planPrice = PlanPrice::create([
            'plan_id' => $plan->id,
            'stripe_price_id' => $stripePrice->id,
            'price' => $priceData['price'],
            'currency' => $priceData['currency'] ?? config('stripe.currency', 'usd'),
            'interval' => $priceData['interval'],
        ]);

        Log::info('Price created for plan', [
            'plan_id' => $plan->id,
            'price_id' => $planPrice->id,
            'stripe_price_id' => $stripePrice->id,
        ]);

        return $planPrice;
    }

    /**
     * Update a plan (name, description)
     */
    public function updatePlan(Plan $plan, array $data): Plan
    {
        return DB::transaction(function () use ($plan, $data) {
            // Update in Stripe
            if ($plan->stripe_product_id) {
                $this->stripeService->updateProduct($plan->stripe_product_id, [
                    'name' => $data['name'] ?? $plan->name,
                    'description' => $data['description'] ?? $plan->description,
                ]);
            }

            // Update local
            $plan->update([
                'name' => $data['name'] ?? $plan->name,
                'description' => $data['description'] ?? $plan->description,
            ]);

            return $plan->fresh();
        });
    }

    /**
     * Archive a plan (deactivate in Stripe, soft-delete locally)
     */
    public function archivePlan(Plan $plan): bool
    {
        return DB::transaction(function () use ($plan) {
            // Archive product in Stripe
            if ($plan->stripe_product_id) {
                $this->stripeService->archiveProduct($plan->stripe_product_id);
            }

            // Archive all prices
            foreach ($plan->prices as $price) {
                if ($price->stripe_price_id) {
                    $this->stripeService->archivePrice($price->stripe_price_id);
                }
            }

            // Delete local plan (cascade deletes prices)
            $plan->delete();

            Log::info('Plan archived', ['plan_id' => $plan->id]);

            return true;
        });
    }

    /**
     * Sync plans from Stripe to local database
     */
    public function syncFromStripe(): array
    {
        $synced = ['products' => 0, 'prices' => 0];

        // Get all active products from Stripe
        $products = $this->stripeService->listProducts(['active' => true, 'limit' => 100]);

        foreach ($products->data as $product) {
            // Find or create local plan
            $plan = Plan::updateOrCreate(
                ['stripe_product_id' => $product->id],
                [
                    'name' => $product->name,
                    'slug' => $product->metadata['slug'] ?? Str::slug($product->name),
                    'description' => $product->description,
                ]
            );
            $synced['products']++;

            // Sync prices for this product
            $prices = $this->stripeService->listPricesForProduct($product->id);

            foreach ($prices->data as $stripePrice) {
                if ($stripePrice->type !== 'recurring') {
                    continue;
                }

                $interval = $stripePrice->recurring->interval === 'year' ? 'yearly' : 'monthly';

                PlanPrice::updateOrCreate(
                    ['stripe_price_id' => $stripePrice->id],
                    [
                        'plan_id' => $plan->id,
                        'price' => $stripePrice->unit_amount,
                        'currency' => $stripePrice->currency,
                        'interval' => $interval,
                    ]
                );
                $synced['prices']++;
            }
        }

        Log::info('Stripe sync completed', $synced);

        return $synced;
    }

    /**
     * Sync local plans to Stripe (create missing products/prices)
     */
    public function syncToStripe(): array
    {
        $synced = ['products' => 0, 'prices' => 0];

        $plans = Plan::with('prices')->get();

        foreach ($plans as $plan) {
            // Create product in Stripe if missing
            if (!$plan->stripe_product_id) {
                $stripeProduct = $this->stripeService->createProduct([
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'metadata' => ['slug' => $plan->slug],
                ]);

                $plan->update(['stripe_product_id' => $stripeProduct->id]);
                $synced['products']++;
            }

            // Create prices in Stripe if missing
            foreach ($plan->prices as $price) {
                if (!$price->stripe_price_id) {
                    $stripePrice = $this->stripeService->createPrice([
                        'product' => $plan->stripe_product_id,
                        'unit_amount' => $price->price,
                        'currency' => $price->currency,
                        'recurring' => [
                            'interval' => $price->interval === 'yearly' ? 'year' : 'month',
                        ],
                    ]);

                    $price->update(['stripe_price_id' => $stripePrice->id]);
                    $synced['prices']++;
                }
            }
        }

        Log::info('Local to Stripe sync completed', $synced);

        return $synced;
    }
}