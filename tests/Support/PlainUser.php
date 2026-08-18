<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A minimal host user model that intentionally omits the cms-framework traits
 * (HasNotifications and HasRolesAndPermissions).
 *
 * It mirrors an application whose User model has never been wired up for the
 * notifications module, and is used to prove the notification manager degrades
 * gracefully instead of fataling with "Call to undefined method" when those
 * relationships are absent. It shares the `users` table, so rows created with
 * the standard user factory are queryable through this model.
 */
class PlainUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
