<?php

/**
 * Updates Web Routes
 *
 * The signed loopback route the self-updater requests to reset PHP-FPM's
 * OPcache after an update run from the CLI or a queue worker (#429).
 *
 * @since      2.16.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Http\Controllers\OpcacheResetController;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\WebOpcacheResetter;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| OPcache Reset Route
|--------------------------------------------------------------------------
|
| Deliberately outside the `web` group: it needs no session or cookies. The
| signature is relative so a proxy that rewrites the host or scheme does not
| invalidate it. The core provider exempts it from maintenance mode, because
| the updater requests it before taking the site back out.
|
*/

Route::get( '/cms-framework/updates/opcache-reset', OpcacheResetController::class )
    ->middleware( 'signed:relative' )
    ->name( WebOpcacheResetter::LOOPBACK_ROUTE );
