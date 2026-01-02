<?php

declare( strict_types = 1 );

/**
 * PostTag Resource for the CMS Framework Blog Module.
 *
 * This resource class transforms post tag model instances into JSON API responses,
 * including tag data and related posts.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON resource for post tag data transformation.
 *
 * Transforms post tag model instances into properly formatted JSON responses
 * for API endpoints, including related posts data.
 *
 * @since 1.0.0
 */
class PostTagResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Converts the post tag model instance into an array suitable for JSON API responses.
     * Includes tag attributes and related posts when loaded.
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
            'permalink'   => $this->permalink,
            'metadata'    => $this->metadata,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
