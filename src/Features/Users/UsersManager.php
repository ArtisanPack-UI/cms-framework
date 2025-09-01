<?php

/**
 * Users Manager
 *
 * Manages CRUD operations and event filters for users and roles.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Users;

use ArtisanPackUI\CMSFramework\Contracts\UserManagerInterface;
use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use TorMorten\Eventy\Facades\Eventy;

/**
 * Class for managing application users and roles.
 *
 * Provides functionality to manage users, roles, and user-specific settings.
 *
 * @since 1.0.0
 */
class UsersManager implements UserManagerInterface
{
    /**
     * Constructor.
     *
     * Initializes the UsersManager.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        // No dependencies needed for user settings directly here anymore.
    }

    /**
     * Retrieves all users.
     *
     * @since 1.0.0
     *
     * @return Collection A collection of User models.
     */
    public function allUsers(): Collection
    {
        /**
         * Filters the collection of all users.
         *
         * @since 1.0.0
         *
         * @param  Collection  $users  Collection of all users.
         */
        return Eventy::filter('ap.cms.users.all', User::all());
    }

    /**
     * Finds a user by their ID.
     *
     * @since 1.0.0
     *
     * @param  int  $userId  The ID of the user to find.
     * @return User|null The User model if found, otherwise null.
     */
    public function findUser(int $userId): ?User
    {
        $user = User::find($userId);

        /**
         * Filters a user found by their ID.
         *
         * @since 1.0.0
         *
         * @param  User|null  $user  The User model if found, otherwise null.
         * @param  int  $userId  The ID of the user being searched for.
         */
        $filtered = Eventy::filter('ap.cms.users.find', $user, $userId);

        // Ensure we return a User object or null
        return ($filtered instanceof User) ? $filtered : $user;
    }

    /**
     * Creates a new user.
     *
     * The `$userData` array should include 'username', 'email', 'password',
     * and optionally 'first_name', 'last_name', 'website', 'bio', 'links', 'settings', and 'role_id'.
     *
     * @since 1.0.0
     *
     * @param  array  $userData  The data for the new user.
     * @return User The newly created User model.
     */
    public function createUser(array $userData): User
    {
        $userData['password'] = Hash::make($userData['password']);
        $user = User::create($userData);

        /**
         * Fires after a new user has been created.
         *
         * @since 1.0.0
         *
         * @param  User  $user  The newly created User model.
         * @param  array  $userData  The original user data array.
         */
        Eventy::action('ap.cms.users.created', $user, $userData);

        return $user;
    }

    /**
     * Updates an existing user.
     *
     * @since 1.0.0
     *
     * @param  array  $userData  The data to update the user with.
     * @param  User  $user  The User model to update.
     * @return bool True on success, false on failure.
     */
    public function updateUser(User $user, array $userData): bool
    {
        if (isset($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        }

        $updated = $user->update($userData);

        if ($updated) {
            /**
             * Fires after a user has been updated.
             *
             * @since 1.0.0
             *
             * @param  User  $user  The updated User model.
             * @param  array  $userData  The data used for the update.
             */
            Eventy::action('ap.cms.users.updated', $user, $userData);
        }

        return $updated;
    }

    /**
     * Deletes a user.
     *
     * @since 1.0.0
     *
     * @param  User  $user  The User model to delete.
     * @return bool|null True on success, false if not found, null on error.
     */
    public function deleteUser(User $user): ?bool
    {
        $deleted = $user->delete();

        if ($deleted) {
            /**
             * Fires after a user has been deleted.
             *
             * @since 1.0.0
             *
             * @param  int  $userId  The ID of the deleted user.
             */
            Eventy::action('ap.cms.users.deleted', $user->id);
        }

        return $deleted;
    }

    /**
     * Retrieves all roles.
     *
     * @since 1.0.0
     *
     * @return Collection A collection of Role models.
     */
    public function allRoles(): Collection
    {
        /**
         * Filters the collection of all roles.
         *
         * @since 1.0.0
         *
         * @param  Collection  $roles  Collection of all roles.
         */
        return Eventy::filter('ap.cms.roles.all', Role::all());
    }

    /**
     * Finds a role by its ID.
     *
     * @since 1.0.0
     *
     * @param  int  $roleId  The ID of the role to find.
     * @return Role|null The Role model if found, otherwise null.
     */
    public function findRole(int $roleId): ?Role
    {
        $role = Role::find($roleId);

        /**
         * Filters a role found by its ID.
         *
         * @since 1.0.0
         *
         * @param  Role|null  $role  The Role model if found, otherwise null.
         * @param  int  $roleId  The ID of the role being searched for.
         */
        $filtered = Eventy::filter('ap.cms.roles.find', $role, $roleId);

        // Ensure we return a Role object or null
        return ($filtered instanceof Role) ? $filtered : $role;
    }

    /**
     * Creates a new role.
     *
     * The `$roleData` array should include 'name', 'slug', 'description', and 'capabilities'.
     *
     * @since 1.0.0
     *
     * @param  array  $roleData  The data for the new role.
     * @return Role The newly created Role model.
     */
    public function createRole(array $roleData): Role
    {
        $role = Role::create($roleData);

        /**
         * Fires after a new role has been created.
         *
         * @since 1.0.0
         *
         * @param  Role  $role  The newly created Role model.
         * @param  array  $roleData  The original role data array.
         */
        Eventy::action('ap.cms.roles.created', $role, $roleData);

        return $role;
    }

    /**
     * Updates an existing role.
     *
     * @since 1.0.0
     *
     * @param  array  $roleData  The data to update the role with.
     * @param  Role  $role  The Role model to update.
     * @return bool True on success, false on failure.
     */
    public function updateRole(Role $role, array $roleData): bool
    {
        $updated = $role->update($roleData);

        if ($updated) {
            /**
             * Fires after a role has been updated.
             *
             * @since 1.0.0
             *
             * @param  Role  $role  The updated Role model.
             * @param  array  $roleData  The data used for the update.
             */
            Eventy::action('ap.cms.roles.updated', $role, $roleData);
        }

        return $updated;
    }

    /**
     * Deletes a role.
     *
     * @since 1.0.0
     *
     * @param  Role  $role  The Role model to delete.
     * @return bool|null True on success, false if not found, null on error.
     */
    public function deleteRole(Role $role): ?bool
    {
        $deleted = $role->delete();

        if ($deleted) {
            /**
             * Fires after a role has been deleted.
             *
             * @since 1.0.0
             *
             * @param  int  $roleId  The ID of the deleted role.
             */
            Eventy::action('ap.cms.roles.deleted', $role->id);
        }

        return $deleted;
    }

    /**
     * Assigns a role to a user.
     *
     * Sets the role_id on the user model.
     *
     * @since 1.0.0
     *
     * @param  Role  $role  The role to assign.
     * @param  User  $user  The user to assign the role to.
     * @return bool True on success, false on failure.
     */
    public function assignRole(User $user, Role $role): bool
    {
        $user->role_id = $role->id;
        $saved = $user->save();

        if ($saved) {
            /**
             * Fires after a role has been assigned to a user.
             *
             * @since 1.0.0
             *
             * @param  User  $user  The user model.
             * @param  Role  $role  The role model.
             */
            Eventy::action('ap.cms.users.role_assigned', $user, $role);
        }

        return $saved;
    }

    /**
     * Removes a role from a user.
     *
     * Sets the role_id on the user model to null.
     *
     * @since 1.0.0
     *
     * @param  User  $user  The user to remove the role from.
     * @return bool True on success, false on failure.
     */
    public function removeRole(User $user): bool
    {
        $user->role_id = null; // Assuming a user can exist without a role, or set to a 'default' role_id if one exists.
        $saved = $user->save();

        if ($saved) {
            /**
             * Fires after a role has been removed from a user.
             *
             * @since 1.0.0
             *
             * @param  User  $user  The user model.
             */
            Eventy::action('ap.cms.users.role_removed', $user);
        }

        return $saved;
    }

    /**
     * Gets a user-specific setting.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The setting key.
     * @param  mixed  $default  Optional. The default value if the setting is not found. Default null.
     * @param  User  $user  The user to retrieve the setting for.
     * @return mixed The setting value.
     */
    public function getUserSetting(User $user, string $key, mixed $default = null): mixed
    {
        return $user->getSetting($key, $default);
    }

    /**
     * Sets a user-specific setting.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The setting key.
     * @param  mixed  $value  The value to set.
     * @param  User  $user  The user to set the setting for.
     * @return bool True if the setting was set and saved, false otherwise.
     */
    public function setUserSetting(User $user, string $key, mixed $value): bool
    {
        return $user->setSetting($key, $value);
    }

    /**
     * Deletes a user-specific setting.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The setting key to delete.
     * @param  User  $user  The user to delete the setting from.
     * @return bool True if the setting was deleted and saved, false otherwise.
     */
    public function deleteUserSetting(User $user, string $key): bool
    {
        return $user->deleteSetting($key);
    }
}
