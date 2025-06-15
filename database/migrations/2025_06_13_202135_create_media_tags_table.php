<?php
/**
 * Create Media Tags Table Migration
 *
 * Creates the database table for storing media tags with name and slug fields
 * to categorize and organize media items.
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
 * Media Tags Table Migration
 *
 * Defines the structure of the media_tags table and handles its creation and removal.
 *
 * @since 1.0.0
 */
return new class extends Migration {
	/**
	 * Run the migrations to create the media_tags table.
	 *
	 * Creates a table with columns for tag name and slug to categorize media items.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void
	{
		Schema::create( 'media_tags', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'name' )->unique();
			$table->string( 'slug' )->unique();
			$table->timestamps();
		} );
	}

	/**
	 * Reverse the migrations.
	 *
	 * Drops the media_tags table if it exists.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists( 'media_tags' );
	}
};
