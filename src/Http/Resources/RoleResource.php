<?php
/**
 * Class RoleResource
 *
 * Resource for transforming role models into JSON responses.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Resources
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Http\Resources;

use ArtisanPackUI\CMSFramework\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class RoleResource
 *
 * Transforms Role models into standardized JSON responses for the API.
 * This resource defines how role data is presented in API responses.
 *
 * @since 1.0.0
 * @mixin Role
 */
class RoleResource extends JsonResource
{
	/**
	 * Transform the resource into an array.
	 *
	 * Converts the Role model into an array representation suitable for JSON responses.
	 * Includes all essential role attributes like ID, name, slug, description, and capabilities.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The current HTTP request.
	 * @return array<string, mixed> The transformed resource array.
	 */
	public function toArray( Request $request ): array
	{
		return [
			'id'           => $this->id,
			'name'         => $this->name,
			'slug'         => $this->slug,
			'description'  => $this->description,
			'capabilities' => $this->capabilities,
			'created_at'   => $this->created_at,
			'updated_at'   => $this->updated_at,
		];
	}
}
