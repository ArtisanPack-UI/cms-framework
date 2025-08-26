<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\MediaCategory;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use TorMorten\Eventy\Facades\Eventy;

class MediaCategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool
    {
        // Handle guest/unauthenticated users
        if (! $user) {
            // Allow guest access to public media category listings
            return Eventy::filter('ap.cms.media_category.can_view_any', true, null);
        }

        // Allow users with category management permissions to view all categories
        if ($user->can('manage_categories')) {
            return true;
        }

        // Allow users to view public media categories
        $canViewCategories = $user->can('read_media_categories');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.media_category.can_view_any', $canViewCategories, $user);
    }

    public function view(?User $user, MediaCategory $mediaCategory): bool
    {
        // Handle guest/unauthenticated users - allow viewing public categories
        if (! $user) {
            // Guests can view public media categories
            return Eventy::filter('ap.cms.media_category.can_view', true, null, $mediaCategory);
        }

        // Allow users with category management permissions to view any category
        if ($user->can('manage_categories')) {
            return true;
        }

        // Allow viewing of public media categories
        $canViewCategory = $user->can('read_media_categories');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.media_category.can_view', $canViewCategory, $user, $mediaCategory);
    }

    public function create(User $user): bool
    {
        // Only allow users with proper permissions to create media categories
        return $user->can('manage_categories');
    }

    public function update(User $user, MediaCategory $mediaCategory): bool
    {
        return $user->can('manage_categories');
    }

    public function delete(User $user, MediaCategory $mediaCategory): bool
    {
        return $user->can('manage_categories');
    }

    public function restore(User $user, MediaCategory $mediaCategory): bool
    {
        return $user->can('manage_categories');
    }

    public function forceDelete(User $user, MediaCategory $mediaCategory): bool
    {
        return $user->can('manage_categories');
    }
}
