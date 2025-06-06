<?php

namespace ArtisanPackUI\CMSFramework\Http\Resources;

use ArtisanPackUI\CMSFramework\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Setting */
class SettingResource extends JsonResource
{
	public function toArray( Request $request ): array
	{
		return [
			'id'         => $this->id,
			'name'       => $this->name,
			'value'      => $this->value,
			'category'   => $this->category,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}
