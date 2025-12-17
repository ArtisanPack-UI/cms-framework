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
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('pages')->onDelete('set null');
            $table->integer('order')->default(0);
            $table->string('template')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('parent_id');
            $table->index('order');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
