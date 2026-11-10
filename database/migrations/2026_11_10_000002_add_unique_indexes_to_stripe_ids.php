<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unique('stripe_subscription_id');
        });

        Schema::table('plan_prices', function (Blueprint $table) {
            $table->unique('stripe_price_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['stripe_subscription_id']);
        });

        Schema::table('plan_prices', function (Blueprint $table) {
            $table->dropUnique(['stripe_price_id']);
        });
    }
};
