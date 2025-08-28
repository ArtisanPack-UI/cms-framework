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
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            
            // Language association
            $table->foreignId('language_id')->constrained()->onDelete('cascade')->comment('Associated language');
            
            // Translation identification
            $table->string('group', 100)->default('default')->comment('Translation group/namespace (e.g., auth, validation)');
            $table->string('key', 500)->comment('Translation key identifier');
            $table->text('value')->nullable()->comment('Translated text value');
            
            // Pluralization support
            $table->json('plurals')->nullable()->comment('Plural forms for languages that support pluralization');
            $table->string('plural_rule', 50)->nullable()->comment('Pluralization rule (e.g., one, few, many, other)');
            
            // Context and metadata
            $table->text('context')->nullable()->comment('Context or description for translators');
            $table->text('comment')->nullable()->comment('Translator comments or notes');
            $table->json('metadata')->nullable()->comment('Additional translation metadata');
            
            // Status and quality tracking
            $table->enum('status', ['pending', 'translated', 'reviewed', 'approved', 'rejected'])
                ->default('pending')->comment('Translation status');
            $table->boolean('needs_review')->default(false)->comment('Whether translation needs review');
            $table->boolean('is_fuzzy')->default(false)->comment('Whether translation is fuzzy/uncertain');
            $table->decimal('quality_score', 3, 2)->nullable()->comment('Translation quality score (0-100)');
            
            // Source tracking
            $table->text('source_value')->nullable()->comment('Original source text (for change detection)');
            $table->timestamp('source_updated_at')->nullable()->comment('When source text was last updated');
            $table->boolean('is_outdated')->default(false)->comment('Whether translation is outdated vs source');
            
            // Translator information
            $table->foreignId('translator_id')->nullable()->constrained('users')->onDelete('set null')
                ->comment('User who provided the translation');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->onDelete('set null')
                ->comment('User who reviewed the translation');
            $table->timestamp('translated_at')->nullable()->comment('When translation was provided');
            $table->timestamp('reviewed_at')->nullable()->comment('When translation was reviewed');
            
            // Usage statistics
            $table->integer('usage_count')->default(0)->comment('Number of times this translation was used');
            $table->timestamp('last_used_at')->nullable()->comment('When this translation was last used');
            
            $table->timestamps();
            
            // Unique constraint for language + group + key combination
            $table->unique(['language_id', 'group', 'key'], 'translations_unique_key');
            
            // Indexes for performance
            $table->index(['language_id', 'group'], 'translations_language_group_index');
            $table->index(['key'], 'translations_key_index');
            $table->index(['status'], 'translations_status_index');
            $table->index(['needs_review'], 'translations_review_index');
            $table->index(['is_outdated'], 'translations_outdated_index');
            $table->index(['translator_id'], 'translations_translator_index');
            $table->index(['usage_count'], 'translations_usage_index');
            $table->index(['last_used_at'], 'translations_last_used_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};