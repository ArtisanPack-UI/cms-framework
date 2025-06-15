<?php
/**
 * Create Media Table Migration
 *
 * Creates the database table for storing media items with all necessary fields
 * for managing files, accessibility attributes, and metadata.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Database\Migrations
 * @since      1.0.0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media Table Migration
 *
 * Defines the structure of the media table and handles its creation and removal.
 *
 * @since 1.0.0
 */
return new class extends Migration {
    /**
     * Run the migrations to create the media table.
     *
     * Creates a table with columns for file information, accessibility attributes,
     * and metadata for media items.
     *
     * @since 1.0.0
     * @return void
     */
    public function up(): void
    {
        Schema::create( 'media', function ( Blueprint $table ) {
            $table->id();
            $table->foreignId( 'user_id' )->constrained()->onDelete( 'cascade' )->comment( 'The user who uploaded this media.' );
            $table->string( 'file_name' );
            $table->string( 'mime_type' );
            $table->string( 'path' )->unique();
            $table->unsignedBigInteger( 'size' );
            $table->string( 'alt_text' )->nullable()->comment( 'Alternative text for accessibility.' );
            $table->boolean( 'is_decorative' )->default( false )->comment( 'True if the image is purely decorative and needs empty alt text.' );
            $table->text( 'caption' )->nullable()->comment( 'The caption for the media item.' );
            $table->json( 'metadata' )->nullable();
            $table->timestamps();
        } );
    }

    /**
     * Reverse the migrations.
     *
     * Drops the media table if it exists.
     *
     * @since 1.0.0
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists( 'media' );
    }
};
