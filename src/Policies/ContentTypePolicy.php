<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\ContentType;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContentTypePolicy
{
    use HandlesAuthorization;

    public function viewAny( User $user ): bool
    {
        return true;
    }

    public function view( User $user, ContentType $contentType ): bool
    {
        return true;
    }

    public function create( User $user ): bool
    {
        // Allow all authenticated users to create content types
        return true;
    }

    public function update( User $user, ContentType $contentType ): bool
    {
        // Allow all authenticated users to update content types
        return true;
    }

    public function delete( User $user, ContentType $contentType ): bool
    {
        return $user->can( 'manage_content_types' );
    }

    public function restore( User $user, ContentType $contentType ): bool
    {
        return $user->can( 'manage_content_types' );
    }

    public function forceDelete( User $user, ContentType $contentType ): bool
    {
        return $user->can( 'manage_content_types' );
    }
}
