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
        Schema::create('featureables', function (Blueprint $table) {
            $table->foreignId('media_id')->constrained('media')->onDelete('cascade');
            $table->morphs('featurable');
            $table->timestamps();

            $table->unique(['media_id', 'featurable_id', 'featurable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('featureables');
    }
};
