<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * plan_id and plan_price_id were plain integer columns. Restricting deletes
 * stops a plan or price from being removed while subscriptions point at it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('plans')->restrictOnDelete();
            $table->foreign('plan_price_id')->references('id')->on('plan_prices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropForeign(['plan_price_id']);
        });
    }
};
