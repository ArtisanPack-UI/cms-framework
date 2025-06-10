<?php

namespace ArtisanPackUI\CMSFramework\Http\Resources;

use ArtisanPackUI\CMSFramework\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Role */
class RoleResource extends JsonResource
{
	public function toArray( Request $request ): array
	{
		return [
			'id'           => $this->id,
			'name'         => $this->name,
			'slug'         => $this->slug,
			'description'  => $this->description,
			'capabilities' => $this->capabilities,
			'created_at'   => $this->created_at,
			'updated_at'   => $this->updated_at,
		];
	}
}
