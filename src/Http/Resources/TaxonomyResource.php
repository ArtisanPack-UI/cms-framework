<?php

namespace ArtisanPackUI\CMSFramework\Http\Resources;

use ArtisanPackUI\CMSFramework\Models\Taxonomy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Taxonomy */
class TaxonomyResource extends JsonResource
{
    public function toArray( Request $request ): array
    {
        return [
            'id'            => $this->id,
            'handle'        => $this->handle,
            'label'         => $this->label,
            'label_plural'  => $this->label_plural,
            'content_types' => $this->content_types,
            'hierarchical'  => $this->hierarchical,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
