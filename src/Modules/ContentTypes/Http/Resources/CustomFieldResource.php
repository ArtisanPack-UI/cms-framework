<?php

declare( strict_types = 1 );

/**
 * CustomField Resource for the CMS Framework ContentTypes Module.
 *
 * This resource class transforms custom field model instances into JSON API responses,
 * including custom field data and associated content types.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON resource for custom field data transformation.
 *
 * Transforms custom field model instances into properly formatted JSON responses
 * for API endpoints, including associated content types.
 *
 * @since 1.0.0
 */
class CustomFieldResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Converts the custom field model instance into an array suitable for JSON API responses.
     * Includes custom field attributes and associated content types.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request instance.
     *
     * @return array<string, mixed> The transformed custom field data array.
     */
    public function toArray( Request $request ): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'key'                => $this->key,
            'type'               => $this->type,
            'column_type'        => $this->column_type,
            'description'        => $this->description,
            'content_types'      => $this->content_types,
            'content_types_list' => $this->getContentTypes()->pluck( 'name', 'slug' )->toArray(),
            'options'            => $this->options,
            'order'              => $this->order,
            'required'           => $this->required,
            'default_value'      => $this->default_value,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
