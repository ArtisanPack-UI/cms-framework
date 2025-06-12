<?php
/**
 * Class RoleRequest
 *
 * Form request for validating role data.
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
 * Class RoleRequest
 *
 * Handles validation of incoming requests for creating or updating roles.
 * Defines the validation rules and authorization logic for role operations.
 *
 * @since 1.0.0
 */
class RoleRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * Defines different validation rules for create and update operations:
	 * - For create (POST): name and slug are required, with slug being unique
	 * - For update (PUT/PATCH): name and slug are optional, with slug being unique except for the current role
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string>> The validation rules.
	 */
	public function rules(): array
	{
		$rules = [
			'description'  => [ 'nullable' ],
			'capabilities' => [ 'nullable', 'array' ],
		];

		// Only require name and slug for store requests
		if ($this->isMethod('POST')) {
			$rules['name'] = [ 'required' ];
			$rules['slug'] = [ 'required', 'unique:roles,slug' ];
		} else {
			$rules['name'] = [ 'sometimes' ];
			// For update requests, we need to ignore the current role's slug
			$rules['slug'] = [ 'sometimes', 'unique:roles,slug,' . $this->route('role') ];
		}

		return $rules;
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
