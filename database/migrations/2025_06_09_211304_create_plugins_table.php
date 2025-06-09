<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create( 'plugins', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'slug' )->unique()->comment( 'The framework-specific slug from the plugin\'s Plugin.php class' );
			$table->string( 'composer_package_name' )->unique()->comment( 'The Composer package name (e.g., vendor/plugin-name)' );
			$table->string( 'directory_name' )->unique()->comment( 'The actual directory name on disk where the plugin is stored' );
			$table->string( 'plugin_class' )->comment( 'Fully qualified class name of the plugin\'s main Plugin.php class' );
			$table->string( 'version' )->default( '1.0.0' );
			$table->boolean( 'is_active' )->default( false );
			$table->json( 'config' )->nullable()->comment( 'Any plugin-specific configuration data' );
			$table->timestamps();
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'plugins' );
	}
};
