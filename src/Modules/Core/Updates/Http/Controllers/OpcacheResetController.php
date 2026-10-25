<?php

/**
 * OPcache Reset Controller.
 *
 * Target of the self-updater's signed loopback request (#429).
 *
 * @since      2.16.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\WebOpcacheResetter;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Resets OPcache inside the web server, for an update or rollback that ran
 * from the CLI or a queue worker and so could not reach PHP-FPM's cache.
 *
 * The route sits behind the `signed:relative` middleware, and the updater
 * signs each URL for one minute only. Its only effect is an `opcache_reset()`,
 * so a leaked URL can do no more than cost the site a few recompiles.
 *
 * @since 2.16.0
 */
class OpcacheResetController extends Controller
{
    /**
     * Reset OPcache and report the outcome.
     *
     * @since 2.16.0
     *
     * @return JsonResponse `opcache` is `reset`, `failed` or `disabled`.
     */
    public function __invoke(): JsonResponse
    {
        $reset = WebOpcacheResetter::resetThisProcess( in_array( PHP_SAPI, ['cli', 'phpdbg'], true ) );

        return response()->json( [
            'opcache'  => match ( $reset ) {
                true    => 'reset',
                false   => 'failed',
                default => 'disabled',
            },
            'php_sapi' => PHP_SAPI,
        ] )->header( 'Cache-Control', 'no-store' );
    }
}
