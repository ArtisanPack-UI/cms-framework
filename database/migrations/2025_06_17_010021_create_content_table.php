<?php
/**
 * Create Content Table Migration
 *
 * Creates the content table for storing all content types in the CMS.
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
 * Migration for creating the content table
 *
 * Creates the main content table that stores all content types in the CMS.
 *
 * @since 1.0.0
 */
return new class extends Migration {
    /**
     * Run the migrations
     *
     * Creates the content table with all necessary columns for storing content items.
     *
     * @since 1.0.0
     * @return void
     */
    public function up(): void
    {
        Schema::create( 'content', function ( Blueprint $table ) {
            $table->id();
            $table->string( 'title' );
            $table->string( 'slug' )->unique();
            $table->longText( 'content' )->nullable();
            $table->string( 'type' )->index();
            $table->string( 'status' )->default( 'draft' );
            $table->foreignId( 'author_id' )->constrained( 'users' )->onDelete( 'cascade' );
            $table->foreignId( 'parent_id' )->nullable()->constrained( 'content' )->onDelete( 'set null' );
            $table->json( 'meta' )->nullable();
            $table->timestamp( 'published_at' )->nullable();
            $table->timestamps();
        } );
    }

    /**
     * Reverse the migrations
     *
     * Drops the content table if it exists.
     *
     * @since 1.0.0
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists( 'contents' );
    }
};
