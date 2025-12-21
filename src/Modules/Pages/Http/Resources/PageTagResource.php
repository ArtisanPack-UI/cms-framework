<?php

declare( strict_types = 1 );

/**
 * PageTag Resource for the CMS Framework Pages Module.
 *
 * This resource class transforms page tag model instances into JSON API responses,
 * including tag data and related pages.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON resource for page tag data transformation.
 *
 * Transforms page tag model instances into properly formatted JSON responses
 * for API endpoints, including related pages data.
 *
 * @since 1.0.0
 */
class PageTagResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Converts the page tag model instance into an array suitable for JSON API responses.
     * Includes tag attributes and related pages when loaded.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request instance.
     *
     * @return array<string, mixed> The transformed tag data array.
     */
    public function toArray( Request $request ): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'order'       => $this->order,
            'permalink'   => $this->permalink,
            'metadata'    => $this->metadata,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
