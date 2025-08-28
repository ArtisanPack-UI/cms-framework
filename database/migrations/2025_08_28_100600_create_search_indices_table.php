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
        Schema::create('search_indices', function (Blueprint $table) {
            $table->id();
            $table->string('searchable_type');
            $table->unsignedBigInteger('searchable_id');
            $table->string('title', 500);
            $table->text('content')->nullable();
            $table->string('excerpt', 500)->nullable();
            $table->text('keywords')->nullable();
            $table->string('type', 100)->nullable();
            $table->string('status', 50)->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->decimal('relevance_boost', 3, 2)->default(1.00);
            $table->json('meta_data')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['searchable_type', 'searchable_id'], 'idx_searchable');
            $table->index(['type', 'status'], 'idx_type_status');
            $table->index('author_id', 'idx_author');
            $table->index('published_at', 'idx_published');
            
            // Full-text search index (MySQL/PostgreSQL compatible)
            $table->fullText(['title', 'content', 'excerpt', 'keywords'], 'search_fulltext');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @since 1.2.0
     */
    public function down(): void
    {
        Schema::dropIfExists('search_indices');
    }
};