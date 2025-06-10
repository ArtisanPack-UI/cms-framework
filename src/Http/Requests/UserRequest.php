<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'username'          => [ 'required' ],
			'email'             => [ 'required', 'email', 'max:254' ],
			'email_verified_at' => [ 'nullable', 'date' ],
			'password'          => [ 'required' ],
			'role_id'           => [ 'required' ],
			'first_name'        => [ 'required' ],
			'last_name'         => [ 'required' ],
			'website'           => [ 'required' ],
			'bio'               => [ 'required' ],
			'links'             => [ 'required' ],
			'settings'          => [ 'required' ],
		];
	}

	public function authorize(): bool
	{
		return true;
	}
}
