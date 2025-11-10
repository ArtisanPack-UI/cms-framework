<?php

/**
 * Post Resource for the CMS Framework Blog Module.
 *
 * This resource class transforms post model instances into JSON API responses,
 * including post data, author, categories, and tags.
 *
 * @since   2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\Blog\Http\Resources
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON resource for post data transformation.
 *
 * Transforms post model instances into properly formatted JSON responses
 * for API endpoints, including related author, categories, and tags data.
 *
 * @since 2.0.0
 */
class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Converts the post model instance into an array suitable for JSON API responses.
     * Includes post attributes and related data when loaded.
     *
     * @since 2.0.0
     *
     * @param  Request  $request  The HTTP request instance.
     * @return array<string, mixed> The transformed post data array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'author_id' => $this->author_id,
            'author' => $this->whenLoaded('author', function () {
                return [
                    'id' => $this->author->id,
                    'name' => $this->author->name,
                    'email' => $this->author->email,
                ];
            }),
            'status' => $this->status,
            'published_at' => $this->published_at,
            'is_published' => $this->isPublished(),
            'permalink' => $this->permalink,
            'metadata' => $this->metadata,
            'categories' => PostCategoryResource::collection($this->whenLoaded('categories')),
            'tags' => PostTagResource::collection($this->whenLoaded('tags')),
            'featured_image_url' => $this->getFeaturedImageUrl(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
