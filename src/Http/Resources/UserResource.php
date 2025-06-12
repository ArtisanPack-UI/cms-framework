<?php

namespace ArtisanPackUI\CMSFramework\Http\Resources;

use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
	public function toArray( Request $request ): array
	{
		return [
			'id'                => $this->id,
			'username'          => $this->username,
			'email'             => $this->email,
			'email_verified_at' => $this->email_verified_at,
			'password'          => $this->password,
			'role_id'           => $this->role_id,
			'first_name'        => $this->first_name,
			'last_name'         => $this->last_name,
			'website'           => $this->website,
			'bio'               => $this->bio,
			'links'             => $this->links,
			'settings'          => $this->settings,
			'created_at'        => $this->created_at,
			'updated_at'        => $this->updated_at,
		];
	}
}
