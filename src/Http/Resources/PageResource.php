<?php

namespace ArtisanPackUI\CMSFramework\Http\Resources;

use ArtisanPackUI\CMSFramework\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Page */
class PageResource extends JsonResource
{
	public function toArray( Request $request ): array
	{
		return [
			'id'         => $this->id,
			'title'      => $this->title,
			'slug'       => $this->slug,
			'content'    => $this->content,
			'status'     => $this->status,
			'parent_id'  => $this->parent_id,
			'order'      => $this->order,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}
