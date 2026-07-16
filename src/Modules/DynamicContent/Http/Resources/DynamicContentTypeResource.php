<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType $resource
 */
class DynamicContentTypeResource extends JsonResource
{
    public function toArray( Request $request ): array
    {
        return [
            'id'          => $this->resource->id,
            'slug'        => $this->resource->slug,
            'name'        => $this->resource->name,
            'cardinality' => $this->resource->cardinality->value,
            'source'      => $this->resource->source->value,
            'description' => $this->resource->description,
            'icon'        => $this->resource->icon,
            'fields'      => $this->whenLoaded( 'fields', function () {
                return $this->resource->fields->map( fn ( $f ) => [
                    'id'       => $f->id,
                    'slug'     => $f->slug,
                    'label'    => $f->label,
                    'type'     => $f->type,
                    'options'  => $f->options ?? [],
                    'default'  => $f->default_value,
                    'required' => $f->required,
                    'order'    => $f->order,
                ] )->all();
            } ),
        ];
    }
}
