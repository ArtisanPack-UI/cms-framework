<?php

/**
 * Role Model
 *
 * Represents a role in the application.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Models;

use ArtisanPackUI\CMSFramework\Services\CacheService;
use ArtisanPackUI\Database\factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use TorMorten\Eventy\Facades\Eventy;

/**
 * Class for the Role model.
 *
 * Handles database interactions for roles, their relationships with users,
 * and associated capabilities stored as a JSON column.
 *
 * @since 1.0.0
 */
class Role extends Model
{
    use HasFactory;

    /**
     * The factory that should be used to instantiate the model.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected static $factory = RoleFactory::class;

    /**
     * The table associated with the model.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $table = 'roles';

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'capabilities',
    ];

    /**
     * The attributes that should be cast.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    protected $casts = [
        'capabilities' => 'array',
    ];

    /**
     * Get the users that belong to this role.
     *
     * @since 1.0.0
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id'); // Specify foreign key for clarity.
    }

    /**
     * Adds a capability to the role.
     *
     * @since 1.0.0
     *
     * @param  string  $capability  The capability to add.
     * @return bool True if the capability was added and saved, false otherwise.
     */
    public function addCapability(string $capability): bool
    {
        /**
         * Filters a capability before it's added to a role.
         *
         * This hook allows for modification or validation of the capability
         * before it's added to the role's capabilities array.
         *
         * @since 1.0.0
         *
         * @param  string  $capability  The capability being added.
         * @param  Role  $role  The role model instance.
         */
        $capability = Eventy::filter('ap.cms.roles.capability_adding', $capability, $this);

        $capabilities = $this->capabilities ?? [];
        if (! $this->hasCapability($capability)) {
            $capabilities[] = $capability;
            $this->capabilities = $capabilities;
            $saved = $this->save();

            if ($saved) {
                // Invalidate all cached capabilities for this role
                $this->getCacheService()->flushByTags(['roles', 'permissions']);

                /**
                 * Fires after a capability has been successfully added to a role.
                 *
                 * @since 1.0.0
                 *
                 * @param  string  $capability  The capability that was added.
                 * @param  Role  $role  The role model instance.
                 */
                Eventy::action('ap.cms.roles.capability_added', $capability, $this);
            }

            return $saved;
        }

        return false;
    }

    /**
     * Get cache service instance.
     */
    private function getCacheService(): CacheService
    {
        return app(CacheService::class);
    }

    /**
     * Checks if the role has a given capability.
     *
     * @since 1.0.0
     *
     * @param  string  $capability  The capability to check for.
     * @return bool True if the role has the capability, false otherwise.
     */
    public function hasCapability(string $capability): bool
    {
        $cacheKey = 'role_capabilities';
        $cacheParams = ['role_id' => $this->id, 'capability' => $capability];

        return $this->getCacheService()->remember(
            'roles',
            $cacheKey,
            function () use ($capability) {
                $capabilities = $this->capabilities;

                // Handle both serialized and unserialized capabilities
                if (is_string($capabilities)) {
                    $capabilities = unserialize($capabilities);
                }

                $hasCapability = in_array($capability, $capabilities ?? [], true);

                /**
                 * Filters whether a role has a specific capability.
                 *
                 * This hook allows for custom logic to determine if a role has a capability,
                 * bypassing or modifying the default capability check if necessary.
                 *
                 * @since 1.0.0
                 *
                 * @param  bool  $hasCapability  Whether the role has the capability.
                 * @param  string  $capability  The capability being checked.
                 * @param  Role  $role  The role model instance.
                 */
                return Eventy::filter('ap.cms.roles.has_capability', $hasCapability, $capability, $this);
            },
            $cacheParams
        );
    }

    /**
     * Removes a capability from the role.
     *
     * @since 1.0.0
     *
     * @param  string  $capability  The capability to remove.
     * @return bool True if the capability was removed and saved, false otherwise.
     */
    public function removeCapability(string $capability): bool
    {
        /**
         * Filters a capability before it's removed from a role.
         *
         * This hook allows for prevention of capability removal or modification
         * of the capability being removed. Return false to prevent removal.
         *
         * @since 1.0.0
         *
         * @param  string|false  $capability  The capability being removed, or false to prevent removal.
         * @param  Role  $role  The role model instance.
         */
        $filteredCapability = Eventy::filter('ap.cms.roles.capability_removing', $capability, $this);

        // If filter returns false, prevent removal
        if ($filteredCapability === false) {
            return false;
        }

        // Use the filtered capability (in case it was modified)
        $capability = $filteredCapability;

        $capabilities = $this->capabilities ?? [];
        if ($this->hasCapability($capability)) {
            $this->capabilities = array_values(array_diff($capabilities, [$capability]));
            $saved = $this->save();

            if ($saved) {
                // Invalidate all cached capabilities for this role
                $this->getCacheService()->flushByTags(['roles', 'permissions']);

                /**
                 * Fires after a capability has been successfully removed from a role.
                 *
                 * @since 1.0.0
                 *
                 * @param  string  $capability  The capability that was removed.
                 * @param  Role  $role  The role model instance.
                 */
                Eventy::action('ap.cms.roles.capability_removed', $capability, $this);
            }

            return $saved;
        }

        return false;
    }
}
