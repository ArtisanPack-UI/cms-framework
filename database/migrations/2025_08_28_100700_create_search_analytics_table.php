<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @since 1.2.0
     */
    public function up(): void
    {
        Schema::create('search_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('query', 500);
            $table->json('filters')->nullable();
            $table->integer('result_count')->default(0);
            $table->decimal('click_through_rate', 5, 4)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address_hash', 64)->nullable(); // Hashed for privacy
            $table->string('user_agent', 500)->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->timestamp('searched_at');
            $table->timestamps();

            // Indexes for analytics queries
            $table->index('query', 'idx_query');
            $table->index('user_id', 'idx_user');
            $table->index('searched_at', 'idx_searched_at');
            $table->index('result_count', 'idx_result_count');
            
            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @since 1.2.0
     */
    public function down(): void
    {
        Schema::dropIfExists('search_analytics');
    }
};