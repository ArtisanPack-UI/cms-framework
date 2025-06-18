<?php
/**
 * Create Term Content Migration
 *
 * Creates the term_content pivot table for managing relationships between terms and content.
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
 * Migration for creating the term_content table
 *
 * Creates a pivot table to establish many-to-many relationships between terms and content.
 *
 * @since 1.0.0
 */
return new class extends Migration {
    /**
     * Run the migrations
     *
     * Creates the term_content table with foreign key constraints to terms and content tables.
     *
     * @since 1.0.0
     * @return void
     */
    public function up(): void
    {
        Schema::create( 'term_content', function ( Blueprint $table ) {
            $table->foreignId( 'term_id' )->constrained( 'terms' )->onDelete( 'cascade' );
            $table->foreignId( 'content_id' )->constrained( 'content' )->onDelete( 'cascade' );
            $table->timestamps();

            $table->primary( [ 'term_id', 'content_id' ] );
        } );
    }

    /**
     * Reverse the migrations
     *
     * Drops the term_content table if it exists.
     *
     * @since 1.0.0
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists( 'term_content' );
    }
};
