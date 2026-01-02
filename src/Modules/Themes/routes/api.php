<?php

/**
 * Themes API Routes
 *
 * Defines RESTful API routes for theme management operations.
 *
 * @since      1.0.0
 */

declare( strict_types = 1 );

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
| - GET    /v1/themes/{slug}       Get theme details
| - POST   /v1/themes/{slug}/activate   Activate a theme
|
*/

Route::middleware( ['auth:sanctum'] )
    ->prefix( 'v1' )
    ->group( function (): void {
        Route::get( '/themes', [ThemesController::class, 'index'] )->name( 'themes.index' );
        Route::get( '/themes/{slug}', [ThemesController::class, 'show'] )->name( 'themes.show' );
        Route::post( '/themes/{slug}/activate', [ThemesController::class, 'activate'] )->name( 'themes.activate' );
    } );
