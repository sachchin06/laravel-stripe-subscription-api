<?php

namespace App\Console\Commands;

use App\Domains\Webhooks\Jobs\HandleCheckoutSessionCompleted;
use App\Domains\Webhooks\Jobs\HandleSubscriptionUpdated;
use App\Domains\Webhooks\Jobs\HandleSubscriptionDeleted;
use App\Domains\Webhooks\Jobs\HandleInvoicePaid;
use App\Domains\Webhooks\Jobs\HandleInvoicePaymentFailed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestWebhookCommand extends Command
{
    protected $signature = 'webhook:test {--dispatch : Actually dispatch test jobs to queue}';
    protected $description = 'Test webhook configuration and optionally dispatch test jobs';

    public function handle(): int
    {
        $this->info('Testing Webhook System...');
        $this->newLine();

        // Test 1: Database connection
        $this->info('1. Testing database connection...');
        try {
            DB::connection()->getPdo();
            $this->info('   ✓ Database connected');
        } catch (\Exception $e) {
            $this->error('   ✗ Database connection failed: ' . $e->getMessage());
            return 1;
        }

        // Test 2: Jobs table exists
        $this->info('2. Checking jobs table...');
        try {
            $jobsCount = DB::table('jobs')->count();
            $failedCount = DB::table('failed_jobs')->count();
            $this->info("   ✓ Jobs table exists (pending: $jobsCount, failed: $failedCount)");
        } catch (\Exception $e) {
            $this->error('   ✗ Jobs table not found: ' . $e->getMessage());
            return 1;
        }

        // Test 3: Queue configuration
        $this->info('3. Checking queue configuration...');
        $queueDriver = config('queue.default');
        $this->info("   Queue driver: $queueDriver");
        if ($queueDriver !== 'database') {
            $this->warn('   ⚠ Queue driver is not set to "database"');
        } else {
            $this->info('   ✓ Queue configured correctly');
        }

        // Test 4: Check Stripe configuration
        $this->info('4. Checking Stripe configuration...');
        $stripeSecret = config('stripe.secret');
        $webhookSecret = config('stripe.webhook.secret');
        
        $this->info('   STRIPE_SECRET: ' . ($stripeSecret && $stripeSecret !== 'sk_test_' ? '✓ configured' : '✗ missing'));
        $this->info('   STRIPE_WEBHOOK_SECRET: ' . ($webhookSecret && $webhookSecret !== 'whsec_' ? '✓ configured' : '✗ missing'));

        // Test 5: List webhook handlers
        $this->newLine();
        $this->info('5. Available Webhook Handlers:');
        $handlers = [
            'checkout.session.completed' => HandleCheckoutSessionCompleted::class,
            'customer.subscription.created' => HandleSubscriptionUpdated::class,
            'customer.subscription.updated' => HandleSubscriptionUpdated::class,
            'customer.subscription.deleted' => HandleSubscriptionDeleted::class,
            'invoice.paid' => HandleInvoicePaid::class,
            'invoice.payment_failed' => HandleInvoicePaymentFailed::class,
        ];

        foreach ($handlers as $event => $handler) {
            $exists = class_exists($handler);
            $this->line("   {$event}: " . ($exists ? '✓' : '✗'));
        }

        // Test 6: Dispatch test job if requested
        if ($this->option('dispatch')) {
            $this->newLine();
            $this->info('6. Dispatching test job...');
            try {
                HandleSubscriptionUpdated::dispatch([
                    'id' => 'sub_test_' . time(),
                    'status' => 'active',
                    'trial_end' => null,
                    'cancel_at' => null,
                ]);
                
                $this->info('   ✓ Test job dispatched');
                $this->info('   Run "php artisan queue:work --once" to process');
            } catch (\Exception $e) {
                $this->error('   ✗ Failed: ' . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info('✓ Webhook test complete!');
        $this->newLine();
        $this->info('To test webhooks:');
        $this->line('  1. Start queue worker: php artisan queue:work --verbose');
        $this->line('  2. Start Stripe CLI: stripe listen --forward-to http://127.0.0.1:8000/api/webhooks/stripe');
        $this->line('  3. Trigger event: stripe trigger checkout.session.completed');

        return 0;
    }
}
