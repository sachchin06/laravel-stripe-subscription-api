<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Store intervals with Stripe's own values ("month", "year"), which the
 * BillingInterval enum uses, instead of "monthly" / "yearly".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('plan_prices')->where('interval', 'monthly')->update(['interval' => 'month']);
        DB::table('plan_prices')->where('interval', 'yearly')->update(['interval' => 'year']);

        Schema::table('plan_prices', function (Blueprint $table) {
            $table->string('interval')->default('month')->change();
        });
    }

    public function down(): void
    {
        Schema::table('plan_prices', function (Blueprint $table) {
            $table->string('interval')->default('monthly')->change();
        });

        DB::table('plan_prices')->where('interval', 'month')->update(['interval' => 'monthly']);
        DB::table('plan_prices')->where('interval', 'year')->update(['interval' => 'yearly']);
    }
};
