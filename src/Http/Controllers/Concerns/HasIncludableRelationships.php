<?php

declare( strict_types=1 );

/**
 * HasIncludableRelationships Trait for API Controllers.
 *
 * Provides on-demand relationship loading via the `include` query parameter.
 * Controllers define an allowlist of includable relationships and optional
 * defaults to maintain backwards compatibility.
 *
 * @since 1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Trait for handling on-demand relationship loading via the include query parameter.
 *
 * Controllers using this trait should define:
 * - `$includableRelationships`: array of allowed relationship names
 * - `$defaultIncludes`: array of relationships to load when no `include` param is present
 *
 * @since 1.1.0
 */
trait HasIncludableRelationships
{
    /**
     * Get the validated relationships to include based on the request.
     *
     * When the `include` query parameter is present, only the requested (and allowed)
     * relationships are returned. When absent, the default includes are returned
     * for backwards compatibility.
     *
     * @since 1.1.0
     *
     * @param  Request  $request  The HTTP request instance.
     *
     * @return array<int, string> The validated relationship names to eager load.
     */
    protected function getRequestedIncludes( Request $request ): array
    {
        $allowable = $this->getIncludableRelationships();

        if ( ! $request->has( 'include' ) ) {
            return $this->getDefaultIncludes();
        }

        $requested = $request->query( 'include', '' );

        if ( empty( $requested ) ) {
            return [];
        }

        $includes = array_map( 'trim', explode( ',', $requested ) );

        return array_values( array_intersect( $includes, $allowable ) );
    }

    /**
     * Get the list of relationships that are allowed to be included.
     *
     * Override this method or define the `$includableRelationships` property
     * on the controller to specify which relationships can be requested.
     *
     * @since 1.1.0
     *
     * @return array<int, string> The allowed relationship names.
     */
    protected function getIncludableRelationships(): array
    {
        return $this->includableRelationships ?? [];
    }

    /**
     * Get the default relationships to load when no include parameter is provided.
     *
     * Override this method or define the `$defaultIncludes` property
     * on the controller to specify default eager loading behaviour.
     *
     * @since 1.1.0
     *
     * @return array<int, string> The default relationship names to eager load.
     */
    protected function getDefaultIncludes(): array
    {
        return $this->defaultIncludes ?? [];
    }
}
