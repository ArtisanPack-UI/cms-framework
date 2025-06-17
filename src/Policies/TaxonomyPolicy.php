<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\Taxonomy;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaxonomyPolicy
{
    use HandlesAuthorization;

    public function viewAny( User $user ): bool
    {
        return true;
    }

    public function view( User $user, Taxonomy $taxonomy ): bool
    {
        return true;
    }

    public function create( User $user ): bool
    {
        // Allow all authenticated users to create taxonomies
        return true;
    }

    public function update( User $user, Taxonomy $taxonomy ): bool
    {
        // Allow all authenticated users to update taxonomies
        return true;
    }

    public function delete( User $user, Taxonomy $taxonomy ): bool
    {
        return $user->can( 'manage_taxonomies' );
    }

    public function restore( User $user, Taxonomy $taxonomy ): bool
    {
        return $user->can( 'manage_taxonomies' );
    }

    public function forceDelete( User $user, Taxonomy $taxonomy ): bool
    {
        return $user->can( 'manage_taxonomies' );
    }
}
