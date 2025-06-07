<?php
/**
 * Class SettingResource
 *
 * Resource class for transforming Setting models into API responses.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Resources
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Http\Resources;

use ArtisanPackUI\CMSFramework\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class SettingResource
 *
 * Transforms Setting models into a standardized API response format.
 * This resource defines how setting data is presented in API responses.
 *
 * @since 1.0.0
 * @mixin Setting
 */
class SettingResource extends JsonResource
{
	/**
	 * Transform the resource into an array.
	 *
	 * Converts the Setting model into an array representation for API responses,
	 * including all relevant attributes of the setting.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The current HTTP request.
	 * @return array<string, mixed> The transformed resource array.
	 */
	public function toArray( Request $request ): array
	{
		return [
			'id'         => $this->id,
			'name'       => $this->name,
			'value'      => $this->value,
			'category'   => $this->category,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}
