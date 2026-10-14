<?php

/**
 * Plugins Web Routes
 *
 * Public route serving the federation bundle a plugin publishes by declaring
 * a relative `federated_module.entry`. Like the theme asset route it is
 * unauthenticated: a build is static bytes, not gated content.
 *
 * @since      2.14.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Http\Controllers\PluginAssetsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Plugin Asset Routes
|--------------------------------------------------------------------------
|
| Serves `/plugins/{slug}/assets/{path}` from the asset directory the plugin
| registered. `{path}` is limited to the characters Vite and the federation
| plugin emit, including `@` and `/` for nested
| `__federation_shared_@scope/name-hash.js` chunks. Containment and extension
| checks live in the controller.
|
*/

Route::get( '/plugins/{slug}/assets/{path}', [PluginAssetsController::class, 'show'] )
    ->where( 'slug', '[A-Za-z0-9_-]+' )
    ->where( 'path', '[A-Za-z0-9._@\-/]+' )
    ->name( 'plugins.assets' );
