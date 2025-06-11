<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
	public function rules(): array
	{
		$rules = [
			'email_verified_at' => [ 'nullable', 'date' ],
			'role_id'           => [ 'nullable' ],
			'first_name'        => [ 'nullable' ],
			'last_name'         => [ 'nullable' ],
			'website'           => [ 'nullable' ],
			'bio'               => [ 'nullable' ],
			'links'             => [ 'nullable', 'array' ],
			'settings'          => [ 'nullable', 'array' ],
		];

		// Only require username, email, and password for store requests
		if ($this->isMethod('POST')) {
			$rules['username'] = [ 'required' ];
			$rules['email'] = [ 'required', 'email', 'max:254' ];
			$rules['password'] = [ 'required' ];
		} else {
			$rules['username'] = [ 'sometimes' ];
			$rules['email'] = [ 'sometimes', 'email', 'max:254' ];
			$rules['password'] = [ 'sometimes' ];
		}

		return $rules;
	}

	public function authorize(): bool
	{
		return true;
	}
}
