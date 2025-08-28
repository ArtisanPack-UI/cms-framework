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
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('error_hash', 64); // Hash of error signature for deduplication
            $table->string('exception_class');
            $table->text('message');
            $table->string('file', 500)->nullable();
            $table->integer('line')->nullable();
            $table->longText('stack_trace')->nullable();
            $table->json('context')->nullable();
            $table->integer('occurrence_count')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->string('severity_level', 20)->default('error');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('request_url', 500)->nullable();
            $table->string('request_method', 10)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('error_hash', 'idx_error_hash');
            $table->index('exception_class', 'idx_exception_class');
            $table->index('first_seen_at', 'idx_first_seen');
            $table->index('last_seen_at', 'idx_last_seen');
            $table->index('resolved_at', 'idx_resolved');
            $table->index('severity_level', 'idx_severity');
            $table->index('user_id', 'idx_user');
            $table->index(['exception_class', 'resolved_at'], 'idx_class_resolved');
            $table->index(['severity_level', 'resolved_at'], 'idx_severity_resolved');
            
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
        Schema::dropIfExists('error_logs');
    }
};