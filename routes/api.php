<?php

use ArtisanPackUI\CMSFramework\Features\Plugins\PluginManager;
use ArtisanPackUI\CMSFramework\Http\Controllers\MediaCategoryController;
use ArtisanPackUI\CMSFramework\Http\Controllers\MediaController;
use ArtisanPackUI\CMSFramework\Http\Controllers\MediaTagController;
use ArtisanPackUI\CMSFramework\Models\Plugin;
use ArtisanPackUI\CMSFramework\Http\Controllers\RoleController;
use ArtisanPackUI\CMSFramework\Http\Controllers\SettingController;
use ArtisanPackUI\CMSFramework\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware( 'api' )->prefix( 'api/cms' )->group( function () {
	// User routes
	Route::apiResource( 'users', UserController::class );

	// Role routes
	Route::apiResource( 'roles', RoleController::class );

	// Settings routes
	Route::apiResource( 'settings', SettingController::class );

	// Settings routes
	Route::apiResource( 'media', MediaController::class )->parameters( [
		'media' => 'media',
	] );

	// Settings routes
	Route::apiResource( 'media-categories', MediaCategoryController::class )->parameters( [
		'media-categories' => 'media_category',
	] );

	// Settings routes
	Route::apiResource( 'media-tags', MediaTagController::class )->parameters( [
		'media-tags' => 'media_tag',
	] );

	// --- Plugin Management Routes ---

	// List all installed plugins
	Route::get( 'plugins', function () {
		return response()->json( Plugin::all() ); // Use renamed Model class
	} );

	// Upload and install a plugin from zip
	Route::post( 'plugins/upload', function ( Request $request, PluginManager $pluginManager ) {
		$request->validate( [
			'plugin_zip' => 'required|file|mimes:zip|max:102400', // Max 100MB
		] );

		try {
			$path     = $request->file( 'plugin_zip' )->store( 'temp_plugin_uploads' );
			$fullPath = storage_path( 'app/' . $path );
			// Internal logic uses updated classes/paths/config
			$plugin = $pluginManager->installFromZip( $fullPath );
			return response()->json( [
				'message' => 'Plugin installed successfully!',
				'plugin'  => $plugin,
			], 201 );
		} catch ( Exception $e ) {
			return response()->json( [
				'message' => 'Plugin installation failed',
				'error'   => $e->getMessage(),
			], 500 );
		} finally {
			if ( isset( $fullPath ) && file_exists( $fullPath ) ) {
				unlink( $fullPath ); // Clean up temp file
			}
		}
	} );

	// Install a plugin from URL
	Route::post( 'plugins/install-from-url', function ( Request $request, PluginManager $pluginManager ) {
		$request->validate( [
			'plugin_url' => 'required|url',
		] );

		try {
			// Internal logic uses updated classes/paths/config
			$plugin = $pluginManager->installFromUrl( sanitizeUrl( $request->input( 'plugin_url' ) ) );
			return response()->json( [
				'message' => 'Plugin installed successfully from URL!',
				'plugin'  => $plugin,
			], 201 );
		} catch ( Exception $e ) {
			return response()->json( [
				'message' => 'Plugin installation failed',
				'error'   => $e->getMessage(),
			], 500 );
		}
	} );

	// Activate a plugin by its slug
	// The '{name}' parameter now corresponds to the plugin's slug
	Route::post( 'plugins/{name}/activate', function ( string $name, PluginManager $pluginManager ) {
		try {
			// Internal logic uses updated classes/paths/config
			$plugin = $pluginManager->activatePlugin( $name );
			return response()->json( [
				'message' => 'Plugin activated successfully!',
				'plugin'  => $plugin,
			] );
		} catch ( Exception $e ) {
			return response()->json( [
				'message' => 'Failed to activate plugin',
				'error'   => $e->getMessage(),
			], 500 );
		}
	} );

	// Deactivate a plugin by its slug
	Route::post( 'plugins/{name}/deactivate', function ( string $name, PluginManager $pluginManager ) {
		try {
			// Internal logic uses updated classes/paths/config
			$plugin = $pluginManager->deactivatePlugin( $name );
			return response()->json( [
				'message' => 'Plugin deactivated successfully!',
				'plugin'  => $plugin,
			] );
		} catch ( Exception $e ) {
			return response()->json( [
				'message' => 'Failed to deactivate plugin',
				'error'   => $e->getMessage(),
			], 500 );
		}
	} );

	// Update a plugin from zip by its slug
	Route::post( 'plugins/{name}/update', function ( string $name, Request $request, PluginManager $pluginManager ) {
		$request->validate( [
			'plugin_zip' => 'required|file|mimes:zip|max:102400', // Max 100MB
		] );

		try {
			$path     = $request->file( 'plugin_zip' )->store( 'temp_plugin_updates' );
			$fullPath = storage_path( 'app/' . $path );
			// Internal logic uses updated classes/paths/config
			$plugin = $pluginManager->updateFromZip( $fullPath, $name );
			return response()->json( [
				'message' => 'Plugin updated successfully!',
				'plugin'  => $plugin,
			] );
		} catch ( Exception $e ) {
			return response()->json( [
				'message' => 'Plugin update failed',
				'error'   => $e->getMessage(),
			], 500 );
		} finally {
			if ( isset( $fullPath ) && file_exists( $fullPath ) ) {
				unlink( $fullPath );
			}
		}
	} );

	// Uninstall a plugin by its slug
	Route::delete( 'plugins/{name}', function ( string $name, PluginManager $pluginManager ) {
		try {
			// Internal logic uses updated classes/paths/config
			$pluginManager->uninstallPlugin( $name );
			return response()->json( [ 'message' => 'Plugin uninstalled successfully!' ] );
		} catch ( Exception $e ) {
			return response()->json( [
				'message' => 'Failed to uninstall plugin',
				'error'   => $e->getMessage(),
			], 500 );
		}
	} );
} );
