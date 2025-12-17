<?php

/**
 * ContentType Policy for the CMS Framework ContentTypes Module.
 *
 * This policy handles authorization for content type-related operations using
 * the artisanpack-ui/hooks filter system for extensible permission checking.
 *
 * @since   2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Policies;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for managing content type permissions.
 *
 * Provides authorization methods for content type-related operations using
 * the artisanpack-ui/hooks system for extensibility.
 *
 * @since 2.0.0
 */
class ContentTypePolicy
{
    /**
     * Determine whether the user can view any content types.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can view content types, false otherwise.
     */
    public function viewAny(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can view any content types.
         *
         * @since 2.0.0
         *
         * @hook  contentTypes.viewAny
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('contentTypes.viewAny', 'contentTypes.manage'));
    }

    /**
     * Determine whether the user can view the content type.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  ContentType  $contentType  The content type instance to check permissions for.
     * @return bool True if the user can view the content type, false otherwise.
     */
    public function view(Authenticatable $user, ContentType $contentType): bool
    {
        /**
         * Filters the capability used to determine whether a user can view a content type.
         *
         * @since 2.0.0
         *
         * @hook  contentTypes.view
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  ContentType  $contentType  The content type being checked.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('contentTypes.view', 'contentTypes.manage', $contentType));
    }

    /**
     * Determine whether the user can create content types.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can create content types, false otherwise.
     */
    public function create(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can create content types.
         *
         * @since 2.0.0
         *
         * @hook  contentTypes.create
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('contentTypes.create', 'contentTypes.manage'));
    }

    /**
     * Determine whether the user can update the content type.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  ContentType  $contentType  The content type instance to check permissions for.
     * @return bool True if the user can update the content type, false otherwise.
     */
    public function update(Authenticatable $user, ContentType $contentType): bool
    {
        /**
         * Filters the capability used to determine whether a user can update content types.
         *
         * @since 2.0.0
         *
         * @hook  contentTypes.update
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  ContentType  $contentType  The content type being updated.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('contentTypes.update', 'contentTypes.manage', $contentType));
    }

    /**
     * Determine whether the user can delete the content type.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  ContentType  $contentType  The content type instance to check permissions for.
     * @return bool True if the user can delete the content type, false otherwise.
     */
    public function delete(Authenticatable $user, ContentType $contentType): bool
    {
        /**
         * Filters the capability used to determine whether a user can delete content types.
         *
         * @since 2.0.0
         *
         * @hook  contentTypes.delete
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  ContentType  $contentType  The content type being deleted.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('contentTypes.delete', 'contentTypes.manage', $contentType));
    }
}
