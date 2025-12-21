<?php

declare( strict_types = 1 );

/**
 * Page Resource for the CMS Framework Pages Module.
 *
 * This resource class transforms page model instances into JSON API responses,
 * including page data, author, categories, tags, and hierarchy information.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON resource for page data transformation.
 *
 * Transforms page model instances into properly formatted JSON responses
 * for API endpoints, including related author, categories, tags, and hierarchy data.
 *
 * @since 1.0.0
 */
class PageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Converts the page model instance into an array suitable for JSON API responses.
     * Includes page attributes, related data, and hierarchy information when loaded.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request instance.
     *
     * @return array<string, mixed> The transformed page data array.
     */
    public function toArray( Request $request ): array
    {
        return [
            'id'        => $this->id,
            'title'     => $this->title,
            'slug'      => $this->slug,
            'content'   => $this->content,
            'excerpt'   => $this->excerpt,
            'author_id' => $this->author_id,
            'author'    => $this->whenLoaded( 'author', function () {
                return [
                    'id'    => $this->author->id,
                    'name'  => $this->author->name,
                    'email' => $this->author->email,
                ];
            } ),
            'parent_id' => $this->parent_id,
            'parent'    => $this->whenLoaded( 'parent', function () {
                return $this->parent ? [
                    'id'    => $this->parent->id,
                    'title' => $this->parent->title,
                    'slug'  => $this->parent->slug,
                ] : null;
            } ),
            'children'           => self::collection( $this->whenLoaded( 'children' ) ),
            'order'              => $this->order,
            'template'           => $this->template,
            'status'             => $this->status,
            'published_at'       => $this->published_at,
            'is_published'       => $this->isPublished(),
            'permalink'          => $this->permalink,
            'breadcrumb'         => $this->breadcrumb,
            'depth'              => $this->depth,
            'metadata'           => $this->metadata,
            'categories'         => PageCategoryResource::collection( $this->whenLoaded( 'categories' ) ),
            'tags'               => PageTagResource::collection( $this->whenLoaded( 'tags' ) ),
            'featured_image_url' => $this->getFeaturedImageUrl(),
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
            'deleted_at'         => $this->deleted_at,
        ];
    }
}
