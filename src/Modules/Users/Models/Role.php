<?php

namespace ArtisanPackUI\CMSFramework\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany( Permission::class );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany( config( 'cms-framework.user_model' ), 'role_user', 'role_id', 'user_id' );
    }
}
