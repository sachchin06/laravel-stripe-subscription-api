<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Products and prices change what is sold in Stripe, so only admins may manage them.
        Gate::define('manage-products', fn (User $user): bool => $user->is_admin === true);
    }
}
