<?php

namespace App\Console\Commands;

use App\Domains\Subscription\Services\ProductService;
use Illuminate\Console\Command;

class StripeSyncCommand extends Command
{
    protected $signature = 'stripe:sync 
                            {direction=from : Sync direction: "from" (Stripe to local) or "to" (local to Stripe)}';

    protected $description = 'Sync products and prices between Stripe and local database';

    public function handle(ProductService $productService): int
    {
        $direction = $this->argument('direction');

        if (!in_array($direction, ['from', 'to'])) {
            $this->error('Invalid direction. Use "from" or "to".');
            return self::FAILURE;
        }

        $this->info("Starting Stripe sync ({$direction})...");

        try {
            if ($direction === 'from') {
                $result = $productService->syncFromStripe();
                $this->info("Synced from Stripe: {$result['products']} products, {$result['prices']} prices");
            } else {
                $result = $productService->syncToStripe();
                $this->info("Synced to Stripe: {$result['products']} products, {$result['prices']} prices");
            }

            $this->info('Sync completed successfully!');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}