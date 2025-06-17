<?php

namespace ArtisanPackUI\CMSFramework\Http\Resources;

use ArtisanPackUI\CMSFramework\Models\ContentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentType */
class ContentTypeResource extends JsonResource
{
    public function toArray( Request $request ): array
    {
        return [
            'id'           => $this->id,
            'handle'       => $this->handle,
            'label'        => $this->label,
            'label_plural' => $this->label_plural,
            'slug'         => $this->slug,
            'definition'   => $this->definition,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
