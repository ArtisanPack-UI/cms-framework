<?php

namespace ArtisanPackUI\CMSFramework\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;

class Role extends Model
{
	protected $fillable = [
		'name',
		'slug',
	];

	public function permissions(): BelongsToMany
	{
		return $this->belongsToMany(Permission::class);
	}

	public function users(): BelongsToMany
	{
		return $this->belongsToMany(User::class);
	}
}