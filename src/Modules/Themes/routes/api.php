<?php

/**
 * Themes API Routes
 *
 * Defines RESTful API routes for theme management operations.
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Themes\Http\Controllers\ThemesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Themes API Routes
|--------------------------------------------------------------------------
|
| All routes require authentication via Laravel Sanctum.
| Routes are prefixed with '/v1' for API versioning.
|
| Available endpoints:
| - GET    /v1/themes              List all themes
| - POST   /v1/themes              Upload and install a theme from a ZIP
| - GET    /v1/themes/updates      List themes with an update available
| - GET    /v1/themes/{slug}       Get theme details
| - POST   /v1/themes/{slug}/activate   Activate a theme
| - POST   /v1/themes/{slug}/update     Update a theme in place
|
*/

Route::middleware( ['auth:sanctum'] )
    ->prefix( 'v1' )
    ->group( function (): void {
        Route::get( '/themes', [ThemesController::class, 'index'] )->name( 'themes.index' );

        // Mutating routes are gated deny-by-default on `manage-themes`: an
        // activated theme's Theme.php and Blade templates execute on every
        // request, so authentication alone is not enough. The `upload` route
        // also enforces the ability through UploadThemeRequest::authorize().
        Route::post( '/themes', [ThemesController::class, 'upload'] )
            ->middleware( 'can:manage-themes' )
            ->name( 'themes.upload' );

        // Registered ahead of `/themes/{slug}` so the literal segment wins;
        // otherwise "updates" is swallowed as a theme slug. Gated on
        // `manage-themes`: `checkUpdates()` makes one synchronous outbound
        // HTTPS request per installed theme, so it is an administrative action
        // with the same blast radius as the mutating routes.
        Route::get( '/themes/updates', [ThemesController::class, 'checkUpdates'] )
            ->middleware( 'can:manage-themes' )
            ->name( 'themes.updates' );

        Route::get( '/themes/{slug}', [ThemesController::class, 'show'] )->name( 'themes.show' );
        Route::post( '/themes/{slug}/activate', [ThemesController::class, 'activate'] )
            ->middleware( 'can:manage-themes' )
            ->name( 'themes.activate' );
        Route::post( '/themes/{slug}/update', [ThemesController::class, 'update'] )
            ->middleware( 'can:manage-themes' )
            ->name( 'themes.update' );
    } );
