<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Http\Controllers\PluginsController;
use Illuminate\Support\Facades\Route;

Route::prefix( 'api/v1' )
    ->middleware( 'auth' )
    ->group( function (): void {
        Route::prefix( 'plugins' )->group( function (): void {
            // List all plugins
            Route::get( '/', [PluginsController::class, 'index'] )->name( 'api.plugins.index' );

            // Check for updates. Gated on `manage-plugins`: `checkUpdates()`
            // makes one synchronous outbound HTTPS request per installed
            // plugin, so it carries the same blast radius as the mutating
            // routes and the same gate.
            Route::get( 'updates', [PluginsController::class, 'checkUpdates'] )
                ->middleware( 'can:manage-plugins' )
                ->name( 'api.plugins.updates' );

            // Get specific plugin
            Route::get( '{slug}', [PluginsController::class, 'show'] )->name( 'api.plugins.show' );

            // Mutating routes are gated deny-by-default on `manage-plugins`:
            // an install/activate extracts and executes attacker-supplied PHP,
            // so authentication alone is not enough. The `install` route also
            // enforces the ability through InstallPluginRequest::authorize().

            // Install plugin (ZIP upload)
            Route::post( 'install', [PluginsController::class, 'install'] )
                ->middleware( 'can:manage-plugins' )
                ->name( 'api.plugins.install' );

            // Activate plugin
            Route::post( '{slug}/activate', [PluginsController::class, 'activate'] )
                ->middleware( 'can:manage-plugins' )
                ->name( 'api.plugins.activate' );

            // Deactivate plugin
            Route::post( '{slug}/deactivate', [PluginsController::class, 'deactivate'] )
                ->middleware( 'can:manage-plugins' )
                ->name( 'api.plugins.deactivate' );

            // Update plugin
            Route::post( '{slug}/update', [PluginsController::class, 'update'] )
                ->middleware( 'can:manage-plugins' )
                ->name( 'api.plugins.update' );

            // Delete plugin
            Route::delete( '{slug}', [PluginsController::class, 'destroy'] )
                ->middleware( 'can:manage-plugins' )
                ->name( 'api.plugins.destroy' );
        } );
    } );
