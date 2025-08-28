<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @since 1.3.0
     */
    public function up(): void
    {
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_name');
            $table->decimal('metric_value', 10, 4);
            $table->string('metric_unit', 50)->default('ms');
            $table->json('tags')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            // Indexes for performance
            $table->index('metric_name', 'idx_metric_name');
            $table->index('recorded_at', 'idx_recorded_at');
            $table->index(['metric_name', 'recorded_at'], 'idx_metric_name_recorded');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @since 1.3.0
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_metrics');
    }
};