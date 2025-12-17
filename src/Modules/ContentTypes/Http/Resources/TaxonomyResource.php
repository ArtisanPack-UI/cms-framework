<?php

/**
 * Taxonomy Resource for the CMS Framework ContentTypes Module.
 *
 * This resource class transforms taxonomy model instances into JSON API responses,
 * including taxonomy data and related content type information.
 *
 * @since   2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON resource for taxonomy data transformation.
 *
 * Transforms taxonomy model instances into properly formatted JSON responses
 * for API endpoints, including related content type data when loaded.
 *
 * @since 2.0.0
 */
class TaxonomyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Converts the taxonomy model instance into an array suitable for JSON API responses.
     * Includes taxonomy attributes and related content type when loaded.
     *
     * @since 2.0.0
     *
     * @param  Request  $request  The HTTP request instance.
     * @return array<string, mixed> The transformed taxonomy data array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'content_type_slug' => $this->content_type_slug,
            'content_type' => $this->whenLoaded('contentType', function () {
                return [
                    'id' => $this->contentType->id,
                    'name' => $this->contentType->name,
                    'slug' => $this->contentType->slug,
                ];
            }),
            'description' => $this->description,
            'hierarchical' => $this->hierarchical,
            'show_in_admin' => $this->show_in_admin,
            'rest_base' => $this->rest_base,
            'metadata' => $this->metadata,
            'terms_table' => $this->getTermsTable(),
            'term_model' => $this->getTermModel(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
