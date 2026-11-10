<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Shared\Services\StripeService;
use App\Enums\BillingInterval;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Manages plans and prices locally and in Stripe.
 *
 * Writes happen in two steps. First the local rows are saved, in a DB
 * transaction with no Stripe calls inside it. Then the Stripe objects are
 * created and their IDs stored. If the Stripe step fails, the local rows stay
 * without Stripe IDs and `php artisan stripe:sync to` completes them later.
 * Each Stripe create call uses an idempotency key derived from the local row,
 * so repeating it within Stripe's 24-hour window cannot create a duplicate.
 */
class ProductService
{
    public function __construct(
        private readonly StripeService $stripeService
    ) {}

    public function createPlanWithPrices(array $planData): Plan
    {
        $plan = DB::transaction(function () use ($planData) {
            $plan = Plan::create([
                'name' => $planData['name'],
                'slug' => $planData['slug'] ?? Str::slug($planData['name']),
                'description' => $planData['description'] ?? null,
            ]);

            foreach ($planData['prices'] ?? [] as $priceData) {
                $plan->prices()->create($this->localPriceAttributes($priceData));
            }

            return $plan;
        });

        $this->pushToStripe($plan->load('prices'));

        return $plan->fresh('prices');
    }

    public function createPriceForPlan(Plan $plan, array $priceData): PlanPrice
    {
        $price = $plan->prices()->create($this->localPriceAttributes($priceData));

        $this->pushToStripe($plan->load('prices'));

        return $price->fresh();
    }

    public function updatePlan(Plan $plan, array $data): Plan
    {
        $attributes = [
            'name' => $data['name'] ?? $plan->name,
            'description' => $data['description'] ?? $plan->description,
        ];

        // Stripe first: if it fails, nothing has changed locally. If the local
        // update then fails, repeating the request is safe because updates are idempotent.
        if ($plan->stripe_product_id) {
            $this->stripeService->updateProduct($plan->stripe_product_id, $attributes);
        }

        $plan->update($attributes);

        return $plan->fresh();
    }

    /**
     * Deactivate the product and its prices in Stripe. Locally, a plan nobody
     * has subscribed to is deleted; a plan with subscriptions is kept but marked
     * inactive, so existing subscribers keep their plan and nobody new can join.
     */
    public function archivePlan(Plan $plan): bool
    {
        if ($plan->stripe_product_id) {
            $this->stripeService->archiveProduct($plan->stripe_product_id);
        }

        foreach ($plan->prices as $price) {
            if ($price->stripe_price_id) {
                $this->stripeService->archivePrice($price->stripe_price_id);
            }
        }

        if ($plan->subscriptions()->exists()) {
            $plan->update(['is_active' => false]);
        } else {
            $plan->delete();
        }

        Log::info('Plan archived', ['plan_id' => $plan->id, 'deleted' => !$plan->exists]);

        return true;
    }

    /**
     * Import active products and recurring prices from Stripe.
     */
    public function syncFromStripe(): array
    {
        $synced = ['products' => 0, 'prices' => 0];

        $products = $this->stripeService->listProducts(['active' => true, 'limit' => 100]);

        // autoPagingIterator fetches the next page from Stripe whenever one runs out,
        // so accounts with more than 100 products are fully imported.
        foreach ($products->autoPagingIterator() as $product) {
            $slug = $product->metadata['slug'] ?? Str::slug($product->name);
            $plan = $this->findPlanForProduct($product->id, $slug);

            $plan->fill([
                'name' => $product->name,
                'description' => $product->description,
                'stripe_product_id' => $product->id,
                // Only active Stripe products are listed, so a plan reactivated in Stripe becomes active here too.
                'is_active' => true,
            ]);

            // Existing plans keep their slug. A new plan gets a free one, so a Stripe
            // product called "Basic" cannot collide with an unrelated local "basic" plan.
            if (!$plan->exists) {
                $plan->slug = $this->uniqueSlug($slug);
            }

            $plan->save();
            $synced['products']++;

            $prices = $this->stripeService->listPricesForProduct($product->id);

            foreach ($prices->autoPagingIterator() as $stripePrice) {
                if ($stripePrice->type !== 'recurring') {
                    continue;
                }

                $interval = BillingInterval::tryFrom($stripePrice->recurring->interval);

                if (!$interval) {
                    continue; // Daily and weekly prices are not offered by this app.
                }

                $price = PlanPrice::where('stripe_price_id', $stripePrice->id)->first()
                    // Or the same local price that has not been pushed to Stripe yet.
                    ?? $plan->prices()
                        ->whereNull('stripe_price_id')
                        ->where('interval', $interval)
                        ->where('price', $stripePrice->unit_amount)
                        ->where('currency', $stripePrice->currency)
                        ->first()
                    ?? new PlanPrice();

                $price->fill([
                    'plan_id' => $plan->id,
                    'stripe_price_id' => $stripePrice->id,
                    'price' => $stripePrice->unit_amount,
                    'currency' => $stripePrice->currency,
                    'interval' => $interval,
                ])->save();
                $synced['prices']++;
            }
        }

        Log::info('Stripe sync completed', $synced);

        return $synced;
    }

    /**
     * The plan already linked to this product, otherwise a local plan with the
     * same slug that has no Stripe product yet (e.g. a seeded plan), otherwise a new one.
     */
    private function findPlanForProduct(string $stripeProductId, string $slug): Plan
    {
        return Plan::where('stripe_product_id', $stripeProductId)->first()
            ?? Plan::where('slug', $slug)->whereNull('stripe_product_id')->first()
            ?? new Plan();
    }

    private function uniqueSlug(string $slug): string
    {
        $candidate = $slug;

        for ($suffix = 2; Plan::where('slug', $candidate)->exists(); $suffix++) {
            $candidate = "{$slug}-{$suffix}";
        }

        return $candidate;
    }

    /**
     * Create any plans and prices that have no Stripe ID yet.
     */
    public function syncToStripe(): array
    {
        $synced = ['products' => 0, 'prices' => 0];

        foreach (Plan::active()->with('prices')->get() as $plan) {
            $created = $this->pushToStripe($plan);
            $synced['products'] += $created['products'];
            $synced['prices'] += $created['prices'];
        }

        Log::info('Local to Stripe sync completed', $synced);

        return $synced;
    }

    /**
     * Create the Stripe product and prices that this plan is missing.
     */
    private function pushToStripe(Plan $plan): array
    {
        $created = ['products' => 0, 'prices' => 0];

        try {
            if (!$plan->stripe_product_id) {
                $product = $this->stripeService->createProduct(
                    array_filter([
                        'name' => $plan->name,
                        'description' => $plan->description,
                        'metadata' => ['slug' => $plan->slug],
                    ]),
                    $this->idempotencyKey('product', $plan)
                );
                $plan->update(['stripe_product_id' => $product->id]);
                $created['products']++;
            }

            foreach ($plan->prices as $price) {
                if ($price->stripe_price_id) {
                    continue;
                }

                $stripePrice = $this->stripeService->createPrice([
                    'product' => $plan->stripe_product_id,
                    'unit_amount' => $price->price,
                    'currency' => $price->currency,
                    'recurring' => [
                        'interval' => $price->interval->value,
                    ],
                    'metadata' => ['plan_id' => $plan->id],
                ], $this->idempotencyKey('price', $price));

                $price->update(['stripe_price_id' => $stripePrice->id]);
                $created['prices']++;
            }
        } catch (\Throwable $e) {
            Log::warning('Plan saved locally but not fully created in Stripe; run "php artisan stripe:sync to" to finish', [
                'plan_id' => $plan->id,
            ]);

            throw $e;
        }

        return $created;
    }

    /**
     * A key unique to this row: its ID plus its creation time, so a re-seeded
     * database that reuses the same IDs does not reuse old keys.
     */
    private function idempotencyKey(string $type, Plan|PlanPrice $model): string
    {
        return "{$type}-{$model->getKey()}-{$model->created_at->getTimestamp()}";
    }

    private function localPriceAttributes(array $priceData): array
    {
        return [
            'price' => $priceData['price'],
            'currency' => $priceData['currency'] ?? config('stripe.currency', 'usd'),
            'interval' => $priceData['interval'],
        ];
    }
}
