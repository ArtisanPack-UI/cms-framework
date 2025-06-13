<?php

namespace ArtisanPackUI\CMSFramework\Http\Resources;

use ArtisanPackUI\CMSFramework\Models\MediaCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MediaCategory */
class MediaCategoryResource extends JsonResource
{
	public function toArray( Request $request ): array
	{
		return [
			'id'         => $this->id,
			'name'       => $this->name,
			'slug'       => $this->slug,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}
