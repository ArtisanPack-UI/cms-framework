<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            
            // Language identification
            $table->string('code', 10)->unique()->comment('Language code (e.g., en, es, fr)');
            $table->string('iso_code', 10)->nullable()->comment('ISO 639-1 or 639-2 language code');
            $table->string('locale', 20)->unique()->comment('Full locale code (e.g., en_US, es_ES)');
            
            // Language information
            $table->string('name')->comment('Native language name');
            $table->string('english_name')->comment('English language name');
            $table->string('flag_emoji', 10)->nullable()->comment('Flag emoji representation');
            $table->string('country_code', 2)->nullable()->comment('Associated country code');
            
            // Display and formatting settings
            $table->boolean('is_rtl')->default(false)->comment('Whether language is right-to-left');
            $table->string('direction', 3)->default('ltr')->comment('Text direction (ltr/rtl)');
            $table->string('date_format')->default('Y-m-d')->comment('Preferred date format');
            $table->string('time_format')->default('H:i')->comment('Preferred time format');
            $table->string('number_format')->default('en')->comment('Number formatting locale');
            
            // Status and configuration
            $table->boolean('is_active')->default(true)->comment('Whether language is available for use');
            $table->boolean('is_default')->default(false)->comment('Whether this is the default language');
            $table->boolean('is_fallback')->default(false)->comment('Whether this language serves as fallback');
            $table->integer('sort_order')->default(0)->comment('Display order in lists');
            
            // Completion and quality metrics
            $table->decimal('completion_percentage', 5, 2)->default(0)->comment('Translation completion percentage');
            $table->integer('total_strings')->default(0)->comment('Total translatable strings');
            $table->integer('translated_strings')->default(0)->comment('Number of translated strings');
            $table->timestamp('last_updated_at')->nullable()->comment('Last translation update');
            
            // Metadata
            $table->json('metadata')->nullable()->comment('Additional language-specific metadata');
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['is_active', 'sort_order'], 'languages_active_order_index');
            $table->index(['is_default'], 'languages_default_index');
            $table->index(['is_fallback'], 'languages_fallback_index');
            $table->index(['completion_percentage'], 'languages_completion_index');
        });

        // Insert default English language
        DB::table('languages')->insert([
            'code' => 'en',
            'iso_code' => 'en',
            'locale' => 'en_US',
            'name' => 'English',
            'english_name' => 'English',
            'flag_emoji' => '🇺🇸',
            'country_code' => 'US',
            'is_rtl' => false,
            'direction' => 'ltr',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'number_format' => 'en',
            'is_active' => true,
            'is_default' => true,
            'is_fallback' => true,
            'sort_order' => 1,
            'completion_percentage' => 100.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};