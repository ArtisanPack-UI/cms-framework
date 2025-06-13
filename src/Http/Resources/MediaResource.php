<?php

namespace ArtisanPackUI\CMSFramework\Http\Resources;

use ArtisanPackUI\CMSFramework\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Media */
class MediaResource extends JsonResource
{
	public function toArray( Request $request ): array
	{
		return [
			'id'            => $this->id,
			'file_name'     => $this->file_name,
			'mime_type'     => $this->mime_type,
			'path'          => $this->path,
			'size'          => $this->size,
			'alt_text'      => $this->alt_text,
			'is_decorative' => $this->is_decorative,
			'metadata'      => $this->metadata,
			'created_at'    => $this->created_at,
			'updated_at'    => $this->updated_at,
		];
	}
}
