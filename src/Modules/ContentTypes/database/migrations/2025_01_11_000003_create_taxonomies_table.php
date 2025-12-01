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
        Schema::create('taxonomies', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // Display name
            $table->string('slug')->unique();          // Machine name (e.g., "post_categories")
            $table->string('content_type_slug');       // Which content type it belongs to
            $table->text('description')->nullable();
            $table->boolean('hierarchical')->default(false); // Can have parent-child?
            $table->boolean('show_in_admin')->default(true);
            $table->string('rest_base')->nullable();   // REST API endpoint base
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('content_type_slug');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxonomies');
    }
};
