<?php

declare( strict_types=1 );

/**
 * Manages the registration and routing of admin pages.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Admin\Managers;

use Illuminate\Support\Facades\Route;

/**
 * Registers admin pages and creates their routes with appropriate middleware.
 *
 * Provides an API to register page slugs with actions and optional capabilities,
 * then materializes them into HTTP routes under the /admin prefix.
 *
 * @since 1.0.0
 */
class AdminPageManager
{
    /**
     * Registered pages keyed by slug.
     *
     * @since 1.0.0
     *
     * @var array<string,array{action:mixed,capability:?string}>
     */
    protected array $pages = [];

    /**
     * Stores the details of a page to be registered.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  The slug for the page route.
     * @param  mixed  $action  The view, closure, or controller action.
     * @param  string|null  $capability  The permission required to view the page.
     */
    public function register( string $slug, mixed $action, ?string $capability ): void
    {
        // Coerce a null or empty capability to the admin-dashboard baseline so an
        // admin page is never registered as an `auth`-only route that any
        // authenticated user can reach. A page that genuinely wants a different
        // gate passes its own capability.
        $capability = ( is_string( $capability ) && '' !== $capability )
            ? $capability
            : 'access_admin_dashboard';

        $this->pages[ $slug ] = [
            'action'     => $action,
            'capability' => $capability,
        ];
    }

    /**
     * Creates all the registered admin page routes with security middleware.
     *
     * The base middleware stack comes from `cms.admin.middleware` (filterable via
     * `ap.cmsFramework.admin.middleware`) so hosts can require the same checks
     * (email verification, two-factor, etc.) on these pages as on their own
     * admin routes. Route names and middleware are set before the HTTP verb so
     * they are indexed even when routes are added to a cached route collection.
     *
     * @since 1.0.0
     * @since 2.13.0 Reads the base middleware stack from config and names routes
     *               before the verb.
     */
    public function registerRoutes(): void
    {
        Route::middleware( $this->middleware() )
            ->prefix( 'admin' )
            ->name( 'admin.' )
            ->group( function (): void {
                foreach ( $this->pages as $slug => $details ) {
                    // Clean the slug to create a predictable route name.
                    $cleanedSlug = preg_replace( '/\/\{.*?\}/', '', $slug );
                    $routeName   = str_replace( '/', '.', $cleanedSlug );

                    Route::name( $routeName )
                        ->middleware( 'can:' . $details['capability'] )
                        ->get( $slug, $details['action'] );
                }
            } );
    }

    /**
     * Resolves the base middleware stack applied to every admin page route.
     *
     * The configured value is normalized to a list of non-empty strings, and
     * replaced with `web` + `auth` when that leaves it empty, before it reaches
     * the `ap.cmsFramework.admin.middleware` filter, so callbacks always get an
     * array. The filtered result is normalized the same way. The fallback only
     * applies to an empty stack: a non-empty stack without an authentication
     * middleware ( e.g. `['web']` ) is used as-is.
     *
     * @since 2.13.0
     *
     * @return array<int,string> The middleware stack.
     */
    protected function middleware(): array
    {
        $default    = [ 'web', 'auth' ];
        $middleware = $this->normalizeMiddleware( config( 'cms.admin.middleware', $default ) );

        $middleware = $this->normalizeMiddleware( applyFilters(
            'ap.cmsFramework.admin.middleware',
            [] === $middleware ? $default : $middleware,
        ) );

        return [] === $middleware ? $default : $middleware;
    }

    /**
     * Reduces a middleware value to a list of non-empty strings.
     *
     * @since 2.13.0
     *
     * @param  mixed  $middleware  Raw configured or filtered value.
     *
     * @return array<int,string> The non-empty string entries, re-indexed.
     */
    protected function normalizeMiddleware( mixed $middleware ): array
    {
        return array_values( array_filter(
            (array) $middleware,
            fn ( mixed $item ): bool => is_string( $item ) && '' !== $item,
        ) );
    }
}
