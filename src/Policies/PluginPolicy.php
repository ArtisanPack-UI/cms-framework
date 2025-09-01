<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\Plugin;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use TorMorten\Eventy\Facades\Eventy;

class PluginPolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool
    {
        // Handle guest/unauthenticated users
        if (! $user) {
            // Guests cannot view plugin listings for security reasons
            return Eventy::filter('ap.cms.plugin.can_view_any', false, null);
        }

        // Allow users with plugin management permissions to view all plugins
        if ($user->can('manage_plugins')) {
            return true;
        }

        // Allow users to view basic plugin information if they can read plugins
        $canViewPlugins = $user->can('read_plugins');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.plugin.can_view_any', $canViewPlugins, $user);
    }

    public function view(?User $user, Plugin $plugin): bool
    {
        // Handle guest/unauthenticated users
        if (! $user) {
            // Guests cannot view individual plugin details for security reasons
            return Eventy::filter('ap.cms.plugin.can_view', false, null, $plugin);
        }

        // Allow users with plugin management permissions to view any plugin
        if ($user->can('manage_plugins')) {
            return true;
        }

        // Allow viewing of basic plugin information if user can read plugins
        $canViewPlugin = $user->can('read_plugins');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.plugin.can_view', $canViewPlugin, $user, $plugin);
    }

    public function create(User $user): bool
    {
        return $user->can('manage_plugins');
    }

    public function update(User $user, Plugin $plugin): bool
    {
        return $user->can('manage_plugins');
    }

    public function delete(User $user, Plugin $plugin): bool
    {
        return $user->can('manage_plugins');
    }

    public function restore(User $user, Plugin $plugin): bool
    {
        return $user->can('manage_plugins');
    }

    public function forceDelete(User $user, Plugin $plugin): bool
    {
        return $user->can('manage_plugins');
    }
}
