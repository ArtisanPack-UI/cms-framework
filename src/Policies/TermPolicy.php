<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\Term;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use TorMorten\Eventy\Facades\Eventy;

class TermPolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool
    {
        // Handle guest/unauthenticated users
        if (! $user) {
            // Allow guest access to public term listings (for front-end display)
            return Eventy::filter('ap.cms.term.can_view_any', true, null);
        }

        // Allow users with term management permissions to view all terms
        if ($user->can('manage_terms')) {
            return true;
        }

        // Allow users to view public terms (for content tagging/categorization)
        $canViewTerms = $user->can('read_terms');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.term.can_view_any', $canViewTerms, $user);
    }

    public function view(?User $user, Term $term): bool
    {
        // Handle guest/unauthenticated users - allow viewing public terms
        if (! $user) {
            // Guests can view public terms
            return Eventy::filter('ap.cms.term.can_view', true, null, $term);
        }

        // Allow users with term management permissions to view any term
        if ($user->can('manage_terms')) {
            return true;
        }

        // Allow viewing of public terms
        $canViewTerm = $user->can('read_terms');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.term.can_view', $canViewTerm, $user, $term);
    }

    public function create(User $user): bool
    {
        return $user->can('manage_terms');
    }

    public function update(User $user, Term $term): bool
    {
        return $user->can('manage_terms');
    }

    public function delete(User $user, Term $term): bool
    {
        return $user->can('manage_terms');
    }

    public function restore(User $user, Term $term): bool
    {
        return $user->can('manage_terms');
    }

    public function forceDelete(User $user, Term $term): bool
    {
        return $user->can('manage_terms');
    }
}
