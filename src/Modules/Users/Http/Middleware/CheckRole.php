<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Users\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Route middleware that enforces an RBAC role check.
 *
 * Aliased as `role` by the Users service provider. Accepts one or more
 * role names or slugs — the user must hold at least one to proceed.
 *
 *     Route::get('/admin', ...)->middleware('role:admin');
 *     Route::get('/admin', ...)->middleware('role:admin,editor');
 */
class CheckRole
{
    public function handle( Request $request, Closure $next, string ...$roles )
    {
        if ( Auth::guest() ) {
            abort( 401 );
        }

        $user = Auth::user();

        if ( ! method_exists( $user, 'hasRole' ) ) {
            abort( 403 );
        }

        foreach ( $roles as $role ) {
            if ( $user->hasRole( $role ) ) {
                return $next( $request );
            }
        }

        abort( 403 );
    }
}
