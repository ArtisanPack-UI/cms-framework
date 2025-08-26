<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\Media;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use TorMorten\Eventy\Facades\Eventy;

class MediaPolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool
    {
        // Handle guest/unauthenticated users
        if (! $user) {
            // Allow guest access to public media listings
            return Eventy::filter('ap.cms.media.can_view_any', true, null);
        }

        // Allow users with media management permissions to view all media
        if ($user->can('edit_files')) {
            return true;
        }

        // Allow users to view public media files
        $canViewMedia = $user->can('read_media');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.media.can_view_any', $canViewMedia, $user);
    }

    public function view(?User $user, Media $media): bool
    {
        // Handle guest/unauthenticated users - allow viewing public media
        if (! $user) {
            // Guests can view public media files
            return Eventy::filter('ap.cms.media.can_view', true, null, $media);
        }

        // Allow users to view their own media files
        if ($user->id === $media->user_id) {
            return true;
        }

        // Allow users with media editing permissions to view any media
        if ($user->can('edit_files')) {
            return true;
        }

        // Allow viewing of public media files
        $canViewMedia = $user->can('read_media');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.media.can_view', $canViewMedia, $user, $media);
    }

    public function create(User $user): bool
    {
        return $user->can('upload_files');
    }

    public function update(User $user, Media $media): bool
    {
        return $user->id === $media->user->id || $user->can('edit_files');
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->can('edit_files');
    }

    public function restore(User $user, Media $media): bool
    {
        return $user->can('edit_files');
    }

    public function forceDelete(User $user, Media $media): bool
    {
        return $user->can('edit_files');
    }
}
