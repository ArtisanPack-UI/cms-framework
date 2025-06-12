<?php
/**
 * Class SettingPolicy
 *
 * Policy for authorizing setting-related actions.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Policies
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Setting;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Class SettingPolicy
 *
 * Defines authorization policies for setting-related actions.
 * This policy determines which users can perform various actions on settings.
 *
 * @since 1.0.0
 */
class SettingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any settings.
     *
     * @since 1.0.0
     *
     * @param User $user The user attempting to view settings.
     * @return bool Whether the user can view settings.
     */
    public function viewAny( User $user ): bool
    {
        // Check if the user has the required ability
        return $user->tokenCan('cms:read') && $user->role && in_array('viewAny_settings', $user->role->capabilities ?? []);
    }

    /**
     * Determine whether the user can view the setting.
     *
     * @since 1.0.0
     *
     * @param User    $user    The user attempting to view the setting.
     * @param Setting $setting The setting being viewed.
     * @return bool Whether the user can view the setting.
     */
    public function view( User $user, Setting $setting ): bool
    {
        // Check if the user has the required ability
        return $user->tokenCan('cms:read') && $user->role && in_array('view_settings', $user->role->capabilities ?? []);
    }

    /**
     * Determine whether the user can create settings.
     *
     * @since 1.0.0
     *
     * @param User $user The user attempting to create a setting.
     * @return bool Whether the user can create settings.
     */
    public function create( User $user ): bool
    {
        // Check if the user has the required ability
        return $user->tokenCan('cms:read') && $user->role && in_array('create_settings', $user->role->capabilities ?? []);
    }

    /**
     * Determine whether the user can update the setting.
     *
     * @since 1.0.0
     *
     * @param User    $user    The user attempting to update the setting.
     * @param Setting $setting The setting being updated.
     * @return bool Whether the user can update the setting.
     */
    public function update( User $user, Setting $setting ): bool
    {
        // Check if the user has the required ability
        return $user->tokenCan('cms:read') && $user->role && in_array('update_settings', $user->role->capabilities ?? []);
    }

    /**
     * Determine whether the user can delete the setting.
     *
     * @since 1.0.0
     *
     * @param User    $user    The user attempting to delete the setting.
     * @param Setting $setting The setting being deleted.
     * @return bool Whether the user can delete the setting.
     */
    public function delete( User $user, Setting $setting ): bool
    {
        // Check if the user has the required ability
        return $user->tokenCan('cms:read') && $user->role && in_array('delete_settings', $user->role->capabilities ?? []);
    }

    /**
     * Determine whether the user can restore the setting.
     *
     * @since 1.0.0
     *
     * @param User    $user    The user attempting to restore the setting.
     * @param Setting $setting The setting being restored.
     * @return bool Whether the user can restore the setting.
     */
    public function restore( User $user, Setting $setting ): bool
    {
        // Check if the user has the required ability
        return $user->tokenCan('cms:read') && $user->role && in_array('update_settings', $user->role->capabilities ?? []);
    }

    /**
     * Determine whether the user can permanently delete the setting.
     *
     * @since 1.0.0
     *
     * @param User    $user    The user attempting to permanently delete the setting.
     * @param Setting $setting The setting being permanently deleted.
     * @return bool Whether the user can permanently delete the setting.
     */
    public function forceDelete( User $user, Setting $setting ): bool
    {
        // Check if the user has the required ability
        return $user->tokenCan('cms:read') && $user->role && in_array('delete_settings', $user->role->capabilities ?? []);
    }
}
