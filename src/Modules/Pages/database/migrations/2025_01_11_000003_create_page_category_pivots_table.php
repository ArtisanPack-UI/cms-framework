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
        Schema::create('page_category_pivots', function (Blueprint $table) {
            $table->foreignId('page_id')->constrained()->onDelete('cascade');
            $table->foreignId('page_category_id')->constrained()->onDelete('cascade');
            $table->primary(['page_id', 'page_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_category_pivots');
    }
};
