<?php

/**
 * Role Model for the CMS Framework Users Module.
 *
 * This model represents user roles within the CMS framework and manages
 * relationships between roles, permissions, and users.
 *
 * @package ArtisanPackUI\CMSFramework\Modules\Users\Models
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role model for managing user roles.
 *
 * Represents user roles within the system and defines relationships
 * with permissions and users through many-to-many relationships.
 *
 * @since 1.0.0
 */
class Role extends Model
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
     * Get the permissions that belong to the role.
     *
     * Defines a many-to-many relationship between roles and permissions.
     *
     * @since 1.0.0
     *
     * @return BelongsToMany The relationship instance.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany( Permission::class );
    }

    /**
     * Get the users that belong to the role.
     *
     * Defines a many-to-many relationship between roles and users using
     * the configurable user model from the cms-framework configuration.
     *
     * @since 1.0.0
     *
     * @return BelongsToMany The relationship instance.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany( config( 'cms-framework.user_model' ), 'role_user', 'role_id', 'user_id' );
    }
}
