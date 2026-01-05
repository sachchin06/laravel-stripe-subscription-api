<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('feature'); // e.g., 'api_calls', 'storage_mb', 'team_members'
            $table->integer('amount')->default(1);
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['user_id', 'feature', 'recorded_at']);
            $table->index(['user_id', 'feature', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};