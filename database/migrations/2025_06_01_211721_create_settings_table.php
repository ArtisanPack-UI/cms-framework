<?php
/**
 * Create Settings Table Migration
 *
 * Creates the settings table in the database to store application configuration settings.
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
 * Migration for creating the settings table
 *
 * This migration creates the settings table for storing application configuration values.
 *
 * @since 1.0.0
 */
return new class extends Migration {
	/**
	 * Run the migrations
	 *
	 * Creates the settings table with columns for id, key, value, type, and timestamps.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void
	{
		Schema::create( 'settings', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'key' )->unique();
			$table->text( 'value' )->nullable();
			$table->string( 'type', 50 )->default( 'string' );
			$table->text( 'description' )->nullable();
			$table->timestamps();
		} );
	}

	/**
	 * Reverse the migrations
	 *
	 * Drops the settings table if it exists.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists( 'settings' );
	}
};
