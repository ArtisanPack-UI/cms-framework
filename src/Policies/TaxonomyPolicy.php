<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\Taxonomy;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use TorMorten\Eventy\Facades\Eventy;

class TaxonomyPolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool
    {
        // Handle guest/unauthenticated users
        if (! $user) {
            // Allow guest access to public taxonomy listings (for front-end display)
            return Eventy::filter('ap.cms.taxonomy.can_view_any', true, null);
        }

        // Allow users with taxonomy management permissions to view all taxonomies
        if ($user->can('manage_taxonomies')) {
            return true;
        }

        // Allow users to view public taxonomies (for content categorization)
        $canViewTaxonomies = $user->can('read_taxonomies');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.taxonomy.can_view_any', $canViewTaxonomies, $user);
    }

    public function view(?User $user, Taxonomy $taxonomy): bool
    {
        // Handle guest/unauthenticated users - allow viewing public taxonomies
        if (! $user) {
            // Guests can view public taxonomies
            return Eventy::filter('ap.cms.taxonomy.can_view', true, null, $taxonomy);
        }

        // Allow users with taxonomy management permissions to view any taxonomy
        if ($user->can('manage_taxonomies')) {
            return true;
        }

        // Allow viewing of public taxonomies
        $canViewTaxonomy = $user->can('read_taxonomies');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.taxonomy.can_view', $canViewTaxonomy, $user, $taxonomy);
    }

    public function create(User $user): bool
    {
        // Only allow users with proper permissions to create taxonomies
        // This is critical - taxonomies define how content is categorized
        return $user->can('manage_taxonomies');
    }

    public function update(User $user, Taxonomy $taxonomy): bool
    {
        // Only allow users with proper permissions to update taxonomies
        // This is critical - modifying taxonomies affects content organization
        return $user->can('manage_taxonomies');
    }

    public function delete(User $user, Taxonomy $taxonomy): bool
    {
        return $user->can('manage_taxonomies');
    }

    public function restore(User $user, Taxonomy $taxonomy): bool
    {
        return $user->can('manage_taxonomies');
    }

    public function forceDelete(User $user, Taxonomy $taxonomy): bool
    {
        return $user->can('manage_taxonomies');
    }
}
