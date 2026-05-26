<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Support;

use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Concerns\HasNotifications;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Concerns\HasRolesAndPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class TestUser extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasNotifications;
    use HasRolesAndPermissions;
    use Notifiable;

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
