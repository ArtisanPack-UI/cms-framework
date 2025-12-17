<?php

/**
 * Middleware to check if a user has a specific admin capability.
 *
 * @since      2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that authorizes requests against a given admin capability.
 *
 * @since 2.0.0
 */
class CheckAdminCapability
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  $capability  The
     *                              capability
     *                              to check
     *                              for.
     */
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        // Gate::authorize will automatically throw a 403 exception if the user is not allowed.
        Gate::authorize($capability);

        return $next($request);
    }
}
