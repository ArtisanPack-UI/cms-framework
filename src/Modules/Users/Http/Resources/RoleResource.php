<?php

/**
 * Role Resource for the CMS Framework Users Module.
 *
 * This resource class transforms role model instances into JSON API responses,
 * including role data and optionally loaded relationships such as permissions.
 *
 * @package ArtisanPackUI\CMSFramework\Modules\Users\Http\Resources
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON resource for role data transformation.
 *
 * Transforms role model instances into properly formatted JSON responses
 * for API endpoints, including related permission data when loaded.
 *
 * @since 1.0.0
 */
class RoleResource extends JsonResource
{
	/**
	 * Transform the resource into an array.
	 *
	 * Converts the role model instance into an array suitable for JSON API responses.
	 * Includes role attributes and conditionally includes permission data when the
	 * permissions relationship is loaded.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The HTTP request instance.
	 *
	 * @return array<string, mixed> The transformed role data array.
	 */
	public function toArray(Request $request): array
	{
		return [
			'id' => $this->id,
			'name' => $this->name,
			'slug' => $this->slug,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
			'permissions' => $this->whenLoaded('permissions', function () {
				return $this->permissions->map(function ($permission) {
					return [
						'id' => $permission->id,
						'name' => $permission->name,
						'slug' => $permission->slug,
					];
				});
			}),
		];
	}
}