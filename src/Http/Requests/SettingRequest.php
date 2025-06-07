<?php
/**
 * Class SettingRequest
 *
 * Form request for validating setting data.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Requests
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Class SettingRequest
 *
 * Handles validation of incoming requests for creating or updating settings.
 * Defines the validation rules and authorization logic for setting operations.
 *
 * @since 1.0.0
 */
class SettingRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * Defines the validation rules for setting attributes:
	 * - name: required
	 * - value: optional
	 * - category: optional
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string>> The validation rules.
	 */
	public function rules(): array
	{
		return [
			'name'     => [ 'required' ],
			'value'    => [ 'nullable' ],
			'category' => [ 'nullable' ],
		];
	}

	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * This method always returns true as the actual authorization is handled
	 * in the controller using policies.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Always returns true.
	 */
	public function authorize(): bool
	{
		return true;
	}
}
