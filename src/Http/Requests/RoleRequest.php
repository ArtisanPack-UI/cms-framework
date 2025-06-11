<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoleRequest extends FormRequest
{
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

	public function authorize(): bool
	{
		return true;
	}
}
