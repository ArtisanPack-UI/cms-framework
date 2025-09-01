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
        Schema::create('performance_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->string('transaction_name');
            $table->decimal('duration_ms', 8, 2)->nullable();
            $table->decimal('memory_usage_mb', 8, 2)->nullable();
            $table->integer('db_query_count')->default(0);
            $table->decimal('db_query_time_ms', 8, 2)->default(0);
            $table->integer('http_status_code')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('request_path', 500)->nullable();
            $table->string('request_method', 10)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('transaction_name', 'idx_transaction_name');
            $table->index('started_at', 'idx_started_at');
            $table->index('duration_ms', 'idx_duration');
            $table->index('user_id', 'idx_user');
            $table->index(['transaction_name', 'started_at'], 'idx_name_started');
            
            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @since 1.3.0
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_transactions');
    }
};