<?php

declare( strict_types = 1 );

/**
 * PageCategory Resource for the CMS Framework Pages Module.
 *
 * This resource class transforms page category model instances into JSON API responses,
 * including category data and hierarchical relationships.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON resource for page category data transformation.
 *
 * Transforms page category model instances into properly formatted JSON responses
 * for API endpoints, including parent and children relationships.
 *
 * @since 1.0.0
 */
class PageCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Converts the page category model instance into an array suitable for JSON API responses.
     * Includes category attributes and hierarchical relationships.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request instance.
     *
     * @return array<string, mixed> The transformed category data array.
     */
    public function toArray( Request $request ): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'parent_id'   => $this->parent_id,
            'parent'      => $this->whenLoaded( 'parent', function () {
                return [
                    'id'   => $this->parent->id,
                    'name' => $this->parent->name,
                    'slug' => $this->parent->slug,
                ];
            } ),
            'children'   => self::collection( $this->whenLoaded( 'children' ) ),
            'order'      => $this->order,
            'permalink'  => $this->permalink,
            'metadata'   => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
