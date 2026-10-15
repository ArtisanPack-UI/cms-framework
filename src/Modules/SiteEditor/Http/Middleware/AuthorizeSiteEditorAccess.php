<?php

/**
 * Authorize Site Editor Access Middleware
 *
 * Gates the Site Editor API on the abilities {@see SiteEditorAbilities}
 * resolves. Every route requires the `read` group, which a user with the
 * `structure` ability also passes, and each create, update and delete
 * route requires its own write group on top. The ability is resolved per
 * request, so a filter a host registers after the routes load still
 * applies. Delete actions take no Form Request, so this middleware is
 * what authorizes them.
 *
 * @since      2.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Middleware;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SiteEditorAbilities;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @since 2.15.0
 */
class AuthorizeSiteEditorAccess
{
    /**
     * Handle an incoming request.
     *
     * @since 2.15.0
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  $group  {@see SiteEditorAbilities::READ}, {@see SiteEditorAbilities::STRUCTURE} or {@see SiteEditorAbilities::CONTENT}.
     *
     * @throws AuthorizationException When the user lacks the group's ability.
     */
    public function handle( Request $request, Closure $next, string $group = SiteEditorAbilities::STRUCTURE ): Response
    {
        if ( ! SiteEditorAbilities::allows( $request->user(), $group ) ) {
            throw new AuthorizationException();
        }

        return $next( $request );
    }
}
