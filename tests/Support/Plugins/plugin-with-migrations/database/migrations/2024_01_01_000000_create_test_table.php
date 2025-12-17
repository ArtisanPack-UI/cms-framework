<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_test_table', function (Blueprint $table) {
            $table->id();
            $table->string('test_field');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_test_table');
    }
};
