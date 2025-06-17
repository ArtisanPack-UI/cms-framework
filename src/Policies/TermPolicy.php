<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\Term;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TermPolicy
{
    use HandlesAuthorization;

    public function viewAny( User $user ): bool
    {
        return true;
    }

    public function view( User $user, Term $term ): bool
    {
        return true;
    }

    public function create( User $user ): bool
    {
        return $user->can( 'manage_terms' );
    }

    public function update( User $user, Term $term ): bool
    {
        return $user->can( 'manage_terms' );
    }

    public function delete( User $user, Term $term ): bool
    {
        return $user->can( 'manage_terms' );
    }

    public function restore( User $user, Term $term ): bool
    {
        return $user->can( 'manage_terms' );
    }

    public function forceDelete( User $user, Term $term ): bool
    {
        return $user->can( 'manage_terms' );
    }
}
