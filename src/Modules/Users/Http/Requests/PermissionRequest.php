<?php

/**
 * Permission Request for the CMS Framework Users Module.
 *
 * This form request handles validation and authorization for permission-related
 * HTTP requests, ensuring data integrity and security.
 *
 * @since   1.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Users\Http\Requests
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for permission validation and authorization.
 *
 * Provides validation rules and authorization logic for permission creation
 * and update operations with proper field validation.
 *
 * @since 1.0.0
 */
class PermissionRequest extends FormRequest
{
	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the user is authorized, false otherwise.
	 */
	public function authorize(): bool
	{
		// Authorization is handled by policies, so we return true here
		return true;
	}

	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The validation rules.
	 */
	public function rules(): array
	{
		$permissionId = $this->route('permission') ?? $this->route('id');
		
		return [
			'name' => [
				'required',
				'string',
				'max:255',
				Rule::unique('permissions', 'name')->ignore($permissionId),
			],
			'slug' => [
				'required',
				'string',
				'max:255',
				'regex:/^[a-z0-9]+(?:\.[a-z0-9]+)*(?:-[a-z0-9]+)*$/',
				Rule::unique('permissions', 'slug')->ignore($permissionId),
			],
		];
	}

	/**
	 * Get custom messages for validator errors.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> The custom error messages.
	 */
	public function messages(): array
	{
		return [
			'name.required' => 'The permission name is required.',
			'name.unique' => 'A permission with this name already exists.',
			'slug.required' => 'The permission slug is required.',
			'slug.regex' => 'The permission slug must be lowercase letters, numbers, dots, and hyphens only (e.g., "user.create", "post.edit").',
			'slug.unique' => 'A permission with this slug already exists.',
		];
	}

	/**
	 * Get custom attributes for validator errors.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> The custom attribute names.
	 */
	public function attributes(): array
	{
		return [
			'name' => 'permission name',
			'slug' => 'permission slug',
		];
	}
}