<?php

declare(strict_types=1);

/**
 * User Manager Interface
 *
 * Defines the contract for user management operations in the CMS framework.
 * This interface provides methods for managing users, roles, and user settings.
 *
 * @since   1.0.0
 *
 * @author  Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Contracts;

use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Support\Collection;

/**
 * User Manager Interface
 *
 * Defines the contract for user management operations including user CRUD operations,
 * role management, and user settings management.
 *
 * @since 1.0.0
 */
interface UserManagerInterface
{
    /**
     * Get all users from the database.
     *
     * @return Collection<User> Collection of all users.
     */
    public function allUsers(): Collection;

    /**
     * Find a specific user by their ID.
     *
     * @param  int  $userId  The ID of the user to find.
     * @return User|null The user if found, null otherwise.
     */
    public function findUser(int $userId): ?User;

    /**
     * Create a new user with the provided data.
     *
     * @param  array  $userData  The user data to create the user with.
     * @return User The created user instance.
     */
    public function createUser(array $userData): User;

    /**
     * Update an existing user with new data.
     *
     * @param  User  $user  The user to update.
     * @param  array  $userData  The new user data.
     * @return bool True if the update was successful, false otherwise.
     */
    public function updateUser(User $user, array $userData): bool;

    /**
     * Delete a user from the database.
     *
     * @param  User  $user  The user to delete.
     * @return bool|null True if deletion was successful, false if failed, null if user not found.
     */
    public function deleteUser(User $user): ?bool;

    /**
     * Get all roles from the database.
     *
     * @return Collection<Role> Collection of all roles.
     */
    public function allRoles(): Collection;

    /**
     * Find a specific role by its ID.
     *
     * @param  int  $roleId  The ID of the role to find.
     * @return Role|null The role if found, null otherwise.
     */
    public function findRole(int $roleId): ?Role;

    /**
     * Create a new role with the provided data.
     *
     * @param  array  $roleData  The role data to create the role with.
     * @return Role The created role instance.
     */
    public function createRole(array $roleData): Role;

    /**
     * Update an existing role with new data.
     *
     * @param  Role  $role  The role to update.
     * @param  array  $roleData  The new role data.
     * @return bool True if the update was successful, false otherwise.
     */
    public function updateRole(Role $role, array $roleData): bool;

    /**
     * Delete a role from the database.
     *
     * @param  Role  $role  The role to delete.
     * @return bool|null True if deletion was successful, false if failed, null if role not found.
     */
    public function deleteRole(Role $role): ?bool;

    /**
     * Assign a role to a user.
     *
     * @param  User  $user  The user to assign the role to.
     * @param  Role  $role  The role to assign.
     * @return bool True if the assignment was successful, false otherwise.
     */
    public function assignRole(User $user, Role $role): bool;

    /**
     * Remove a role from a user.
     *
     * @param  User  $user  The user to remove the role from.
     * @return bool True if the removal was successful, false otherwise.
     */
    public function removeRole(User $user): bool;

    /**
     * Get a user setting value.
     *
     * @param  User  $user  The user to get the setting for.
     * @param  string  $key  The setting key.
     * @param  mixed  $default  The default value if the setting doesn't exist.
     * @return mixed The setting value or the default value.
     */
    public function getUserSetting(User $user, string $key, mixed $default): mixed;

    /**
     * Set a user setting value.
     *
     * @param  User  $user  The user to set the setting for.
     * @param  string  $key  The setting key.
     * @param  mixed  $value  The setting value.
     * @return bool True if the setting was saved successfully, false otherwise.
     */
    public function setUserSetting(User $user, string $key, mixed $value): bool;

    /**
     * Delete a user setting.
     *
     * @param  User  $user  The user to delete the setting for.
     * @param  string  $key  The setting key to delete.
     * @return bool True if the setting was deleted successfully, false otherwise.
     */
    public function deleteUserSetting(User $user, string $key): bool;
}
