<?php

/**
 * Setting Resource for the CMS Framework Settings Module.
 *
 * This resource class transforms setting model instances into JSON API responses,
 * including setting data and optionally loaded relationships.
 *
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON resource for setting data transformation.
 *
 * Transforms setting model instances into properly formatted JSON responses
 * for API endpoints, including related permission data when loaded.
 *
 * @since 1.0.0
 */
class SettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Converts the setting model instance into an array suitable for JSON API responses.
     * Includes setting attributes and conditionally includes permission data when the
     * permissions relationship is loaded.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The HTTP request instance.
     * @return array<string, mixed> The transformed setting data array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'value' => $this->value,
            'type' => $this->type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
