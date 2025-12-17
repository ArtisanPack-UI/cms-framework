<?php

/**
 * CustomField Policy for the CMS Framework ContentTypes Module.
 *
 * This policy handles authorization for custom field-related operations using
 * the artisanpack-ui/hooks filter system for extensible permission checking.
 *
 * @since   2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Policies;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\CustomField;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for managing custom field permissions.
 *
 * Provides authorization methods for custom field-related operations using
 * the artisanpack-ui/hooks system for extensibility.
 *
 * @since 2.0.0
 */
class CustomFieldPolicy
{
    /**
     * Determine whether the user can view any custom fields.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can view custom fields, false otherwise.
     */
    public function viewAny(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can view any custom fields.
         *
         * @since 2.0.0
         *
         * @hook  customFields.viewAny
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('customFields.viewAny', 'customFields.manage'));
    }

    /**
     * Determine whether the user can view the custom field.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  CustomField  $customField  The custom field instance to check permissions for.
     * @return bool True if the user can view the custom field, false otherwise.
     */
    public function view(Authenticatable $user, CustomField $customField): bool
    {
        /**
         * Filters the capability used to determine whether a user can view a custom field.
         *
         * @since 2.0.0
         *
         * @hook  customFields.view
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  CustomField  $customField  The custom field being checked.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('customFields.view', 'customFields.manage', $customField));
    }

    /**
     * Determine whether the user can create custom fields.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @return bool True if the user can create custom fields, false otherwise.
     */
    public function create(Authenticatable $user): bool
    {
        /**
         * Filters the capability used to determine whether a user can create custom fields.
         *
         * @since 2.0.0
         *
         * @hook  customFields.create
         *
         * @param  string  $capability  Default capability slug to check.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('customFields.create', 'customFields.manage'));
    }

    /**
     * Determine whether the user can update the custom field.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  CustomField  $customField  The custom field instance to check permissions for.
     * @return bool True if the user can update the custom field, false otherwise.
     */
    public function update(Authenticatable $user, CustomField $customField): bool
    {
        /**
         * Filters the capability used to determine whether a user can update custom fields.
         *
         * @since 2.0.0
         *
         * @hook  customFields.update
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  CustomField  $customField  The custom field being updated.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('customFields.update', 'customFields.manage', $customField));
    }

    /**
     * Determine whether the user can delete the custom field.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The authenticated user to check capabilities for.
     * @param  CustomField  $customField  The custom field instance to check permissions for.
     * @return bool True if the user can delete the custom field, false otherwise.
     */
    public function delete(Authenticatable $user, CustomField $customField): bool
    {
        /**
         * Filters the capability used to determine whether a user can delete custom fields.
         *
         * @since 2.0.0
         *
         * @hook  customFields.delete
         *
         * @param  string  $capability  Default capability slug to check.
         * @param  CustomField  $customField  The custom field being deleted.
         * @return string Filtered capability slug.
         */
        return $user->can(applyFilters('customFields.delete', 'customFields.manage', $customField));
    }
}
