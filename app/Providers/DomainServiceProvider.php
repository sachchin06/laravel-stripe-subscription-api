<?php

namespace App\Providers;

use App\Domains\Shared\Services\StripeService;
use App\Domains\Auth\Services\AuthService;
use App\Domains\Subscription\Services\SubscriptionManager;
use App\Domains\Subscription\Services\PlanService;
use App\Domains\Subscription\Services\FeatureGateService;
use App\Domains\Subscription\Services\UsageTrackingService;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Billing\Services\InvoiceService;
use App\Domains\Webhooks\Services\WebhookService;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for registering domain services
 * 
 * This provider registers all domain services as singletons
 * to ensure consistent instances across the application.
 */
class DomainServiceProvider extends ServiceProvider
{
    /**
     * Register domain services
     */
    public function register(): void
    {
        // Shared services
        $this->app->singleton(StripeService::class);

        // Auth domain services
        $this->app->singleton(AuthService::class);

        // Subscription domain services
        $this->app->singleton(SubscriptionManager::class);
        $this->app->singleton(PlanService::class);
        $this->app->singleton(FeatureGateService::class);
        $this->app->singleton(UsageTrackingService::class);

        // Billing domain services
        $this->app->singleton(BillingService::class);
        $this->app->singleton(InvoiceService::class);

        // Webhook domain services
        $this->app->singleton(WebhookService::class);
    }

    /**
     * Bootstrap domain services
     */
    public function boot(): void
    {
        //
    }
}