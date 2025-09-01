<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\ContentType;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use TorMorten\Eventy\Facades\Eventy;

class ContentTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool
    {
        // Handle guest/unauthenticated users
        if (! $user) {
            // Allow guest access to public content type listings (for front-end display)
            return Eventy::filter('ap.cms.content_type.can_view_any', true, null);
        }

        // Allow users with content type management permissions to view all types
        if ($user->can('manage_content_types')) {
            return true;
        }

        // Allow users to view public content types (for content creation)
        $canViewTypes = $user->can('read_content_types');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.content_type.can_view_any', $canViewTypes, $user);
    }

    public function view(?User $user, ContentType $contentType): bool
    {
        // Handle guest/unauthenticated users - allow viewing public content types
        if (! $user) {
            // Guests can view public content types
            return Eventy::filter('ap.cms.content_type.can_view', true, null, $contentType);
        }

        // Allow users with content type management permissions to view any type
        if ($user->can('manage_content_types')) {
            return true;
        }

        // Allow viewing of public content types
        $canViewType = $user->can('read_content_types');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.content_type.can_view', $canViewType, $user, $contentType);
    }

    public function create(User $user): bool
    {
        // Only allow users with proper permissions to create content types
        // This is critical - content types define the structure of the CMS
        return $user->can('manage_content_types');
    }

    public function update(User $user, ContentType $contentType): bool
    {
        // Only allow users with proper permissions to update content types
        // This is critical - modifying content types affects all related content
        return $user->can('manage_content_types');
    }

    public function delete(User $user, ContentType $contentType): bool
    {
        return $user->can('manage_content_types');
    }

    public function restore(User $user, ContentType $contentType): bool
    {
        return $user->can('manage_content_types');
    }

    public function forceDelete(User $user, ContentType $contentType): bool
    {
        return $user->can('manage_content_types');
    }
}
