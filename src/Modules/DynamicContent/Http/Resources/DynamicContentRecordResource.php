<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentRecord $resource
 */
class DynamicContentRecordResource extends JsonResource
{
    public function toArray( Request $request ): array
    {
        return [
            'id'     => $this->resource->id,
            'label'  => $this->resource->label,
            'order'  => $this->resource->order,
            'values' => $this->resource->fieldValues(),
            'type'   => [
                'slug' => $this->resource->type?->slug,
                'name' => $this->resource->type?->name,
            ],
        ];
    }
}
