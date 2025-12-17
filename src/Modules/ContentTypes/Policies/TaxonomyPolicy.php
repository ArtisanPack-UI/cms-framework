<?php

/**
 * Taxonomy Policy for the CMS Framework ContentTypes Module.
 *
 * This policy handles authorization for taxonomy-related operations using
 * the artisanpack-ui/hooks filter system for extensible permission checking.
 *
 * @since   2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Policies;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Taxonomy;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for managing taxonomy permissions.
 *
 * Provides authorization methods for taxonomy-related operations using
 * the artisanpack-ui/hooks system for extensibility.
 *
 * @since 2.0.0
 */
class TaxonomyPolicy
{
    /**
     * Determine whether the user can view any taxonomies.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can view taxonomies, false otherwise.
     */
    public function viewAny(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can view any taxonomies.
         *
         * @since 2.0.0
         *
         * @hook  taxonomies.viewAny
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('taxonomies.viewAny', 'taxonomies.manage'));
    }

    /**
     * Determine whether the user can view the taxonomy.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  Taxonomy  $taxonomy  The taxonomy instance to check permissions for.
     * @return bool True if the user can view the taxonomy, false otherwise.
     */
    public function view(Authenticatable $user, Taxonomy $taxonomy): bool
    {
        /**
         * Filters the capability used to determine whether a user can view a taxonomy.
         *
         * @since 2.0.0
         *
         * @hook  taxonomies.view
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  Taxonomy  $taxonomy  The taxonomy being checked.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('taxonomies.view', 'taxonomies.manage', $taxonomy));
    }

    /**
     * Determine whether the user can create taxonomies.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can create taxonomies, false otherwise.
     */
    public function create(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can create taxonomies.
         *
         * @since 2.0.0
         *
         * @hook  taxonomies.create
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('taxonomies.create', 'taxonomies.manage'));
    }

    /**
     * Determine whether the user can update the taxonomy.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  Taxonomy  $taxonomy  The taxonomy instance to check permissions for.
     * @return bool True if the user can update the taxonomy, false otherwise.
     */
    public function update(Authenticatable $user, Taxonomy $taxonomy): bool
    {
        /**
         * Filters the capability used to determine whether a user can update taxonomies.
         *
         * @since 2.0.0
         *
         * @hook  taxonomies.update
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  Taxonomy  $taxonomy  The taxonomy being updated.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('taxonomies.update', 'taxonomies.manage', $taxonomy));
    }

    /**
     * Determine whether the user can delete the taxonomy.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  Taxonomy  $taxonomy  The taxonomy instance to check permissions for.
     * @return bool True if the user can delete the taxonomy, false otherwise.
     */
    public function delete(Authenticatable $user, Taxonomy $taxonomy): bool
    {
        /**
         * Filters the capability used to determine whether a user can delete taxonomies.
         *
         * @since 2.0.0
         *
         * @hook  taxonomies.delete
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  Taxonomy  $taxonomy  The taxonomy being deleted.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('taxonomies.delete', 'taxonomies.manage', $taxonomy));
    }
}
