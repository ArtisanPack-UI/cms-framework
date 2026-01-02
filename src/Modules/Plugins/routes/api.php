<?php

declare( strict_types = 1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Http\Controllers\PluginsController;
use Illuminate\Support\Facades\Route;

Route::prefix( 'api/v1' )
    ->middleware( 'auth' )
    ->group( function (): void {
        Route::prefix( 'plugins' )->group( function (): void {
            // List all plugins
            Route::get( '/', [PluginsController::class, 'index'] )->name( 'api.plugins.index' );

            // Check for updates
            Route::get( 'updates', [PluginsController::class, 'checkUpdates'] )->name( 'api.plugins.updates' );

            // Get specific plugin
            Route::get( '{slug}', [PluginsController::class, 'show'] )->name( 'api.plugins.show' );

            // Install plugin (ZIP upload)
            Route::post( 'install', [PluginsController::class, 'install'] )->name( 'api.plugins.install' );

            // Activate plugin
            Route::post( '{slug}/activate', [PluginsController::class, 'activate'] )->name( 'api.plugins.activate' );

            // Deactivate plugin
            Route::post( '{slug}/deactivate', [PluginsController::class, 'deactivate'] )->name( 'api.plugins.deactivate' );

            // Update plugin
            Route::post( '{slug}/update', [PluginsController::class, 'update'] )->name( 'api.plugins.update' );

            // Delete plugin
            Route::delete( '{slug}', [PluginsController::class, 'destroy'] )->name( 'api.plugins.destroy' );
        } );
    });
