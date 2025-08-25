<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\MediaTag;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use TorMorten\Eventy\Facades\Eventy;

class MediaTagPolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool
    {
        // Handle guest/unauthenticated users
        if (! $user) {
            // Allow guest access to public media tag listings
            return Eventy::filter('ap.cms.media_tag.can_view_any', true, null);
        }

        // Allow users with tag management permissions to view all tags
        if ($user->can('manage_categories')) {
            return true;
        }

        // Allow users to view public media tags
        $canViewTags = $user->can('read_media_tags');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.media_tag.can_view_any', $canViewTags, $user);
    }

    public function view(?User $user, MediaTag $mediaTag): bool
    {
        // Handle guest/unauthenticated users - allow viewing public tags
        if (! $user) {
            // Guests can view public media tags
            return Eventy::filter('ap.cms.media_tag.can_view', true, null, $mediaTag);
        }

        // Allow users with tag management permissions to view any tag
        if ($user->can('manage_categories')) {
            return true;
        }

        // Allow viewing of public media tags
        $canViewTag = $user->can('read_media_tags');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.media_tag.can_view', $canViewTag, $user, $mediaTag);
    }

    public function create(User $user): bool
    {
        // Only allow users with proper permissions to create media tags
        return $user->can('manage_categories');
    }

    public function update(User $user, MediaTag $mediaTag): bool
    {
        return $user->can('manage_categories');
    }

    public function delete(User $user, MediaTag $mediaTag): bool
    {
        return $user->can('manage_categories');
    }

    public function restore(User $user, MediaTag $mediaTag): bool
    {
        return $user->can('manage_categories');
    }

    public function forceDelete(User $user, MediaTag $mediaTag): bool
    {
        return $user->can('manage_categories');
    }
}
