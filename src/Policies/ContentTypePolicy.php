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
        return $user->can( 'manage_content_types' );
    }

    public function update( User $user, ContentType $contentType ): bool
    {
        return $user->can( 'manage_content_types' );
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
