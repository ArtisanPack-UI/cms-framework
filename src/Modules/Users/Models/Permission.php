<?php

/**
 * Permission Model for the CMS Framework Users Module.
 *
 * This model represents user permissions within the CMS framework and manages
 * the relationship between permissions and roles.
 *
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permission model for managing user permissions.
 *
 * Represents user permissions within the system and defines the relationship
 * with roles through a many-to-many relationship.
 *
 * @since 1.0.0
 */
class Permission extends Model
{
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
    ];

    /**
     * Get the roles that belong to the permission.
     *
     * Defines a many-to-many relationship between permissions and roles.
     *
     * @since 1.0.0
     *
     * @return BelongsToMany The relationship instance.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
