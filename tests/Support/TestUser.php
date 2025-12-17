<?php

namespace ArtisanPackUI\CMSFramework\Tests\Support;

use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Concerns\HasNotifications;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Concerns\HasRolesAndPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class TestUser extends Authenticatable
{
    use HasFactory, HasNotifications, HasRolesAndPermissions, Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \ArtisanPackUI\Database\Factories\UserFactory::new();
    }
}
