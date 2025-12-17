<?php

/**
 * ContentType Resource for the CMS Framework ContentTypes Module.
 *
 * This resource class transforms content type model instances into JSON API responses,
 * including content type data and custom fields count.
 *
 * @since   2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON resource for content type data transformation.
 *
 * Transforms content type model instances into properly formatted JSON responses
 * for API endpoints, including custom fields count.
 *
 * @since 2.0.0
 */
class ContentTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Converts the content type model instance into an array suitable for JSON API responses.
     * Includes content type attributes and custom fields count.
     *
     * @since 2.0.0
     *
     * @param  Request  $request  The HTTP request instance.
     * @return array<string, mixed> The transformed content type data array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'table_name' => $this->table_name,
            'model_class' => $this->model_class,
            'description' => $this->description,
            'hierarchical' => $this->hierarchical,
            'has_archive' => $this->has_archive,
            'archive_slug' => $this->archive_slug,
            'supports' => $this->supports,
            'metadata' => $this->metadata,
            'public' => $this->public,
            'show_in_admin' => $this->show_in_admin,
            'icon' => $this->icon,
            'menu_position' => $this->menu_position,
            'custom_fields_count' => $this->custom_fields_count ?? $this->getCustomFields()->count(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
