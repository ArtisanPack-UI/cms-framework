<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use TorMorten\Eventy\Facades\Eventy;

class ContentPolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool
    {
        // Handle guest/unauthenticated users
        if (! $user) {
            // Allow guest access to published content by default
            return Eventy::filter('ap.cms.content.can_view_any', true, null);
        }

        // Allow users with content management permissions to view all content
        if ($user->can('manage_content')) {
            return true;
        }

        // Allow users to view published content they have permission to read
        $canViewPublished = $user->can('read_published_content');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.content.can_view_any', $canViewPublished, $user);
    }

    public function view(?User $user, Content $content): bool
    {
        // Handle guest/unauthenticated users - only allow viewing published content
        if (! $user) {
            $canViewPublic = $content->status === 'published';

            return Eventy::filter('ap.cms.content.can_view', $canViewPublic, null, $content);
        }

        // Check if content is published and user can view published content
        if ($content->status === 'published') {
            $canView = $this->viewAny($user);

            return Eventy::filter('ap.cms.content.can_view', $canView, $user, $content);
        }

        // Allow authors to view their own content regardless of status
        if ($user->id === $content->author_id) {
            return true;
        }

        // Allow users with content editing permissions to view unpublished content
        if ($user->can('edit_content')) {
            return true;
        }

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.content.can_view', false, $user, $content);
    }

    public function create(User $user): bool
    {
        // Allow all authenticated users to create content
        return true;
    }

    public function update(User $user, Content $content): bool
    {
        return $user->id === $content->author_id || $user->can('edit_content');
    }

    public function delete(User $user, Content $content): bool
    {
        return $user->id === $content->author_id || $user->can('delete_content');
    }

    public function restore(User $user, Content $content): bool
    {
        return $user->can('edit_content');
    }

    public function forceDelete(User $user, Content $content): bool
    {
        return $user->can('delete_content');
    }
}
