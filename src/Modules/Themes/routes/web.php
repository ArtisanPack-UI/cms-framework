<?php

/**
 * Themes Web Routes
 *
 * Public routes for serving static theme assets bundled inside the theme's
 * `assets/` directory. Unlike the API routes, these are unauthenticated —
 * theme CSS/JS/fonts are static bytes, not gated content.
 *
 * @since      2.5.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Themes\Http\Controllers\ThemeAssetsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Theme Asset Routes
|--------------------------------------------------------------------------
|
| Serves files from `themes/{slug}/assets/{path}` over HTTP so themes can
| enqueue their own CSS/JS/fonts without publishing anything to the host
| app. Path-traversal and extension checks live in the controller.
|
*/

Route::get( '/themes/{slug}/assets/{path}', [ThemeAssetsController::class, 'show'] )
    ->where( 'path', '.*' )
    ->name( 'themes.assets' );
