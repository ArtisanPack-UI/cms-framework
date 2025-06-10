<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoleRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'name'         => [ 'required' ],
			'slug'         => [ 'required' ],
			'description'  => [ 'required' ],
			'capabilities' => [ 'required' ],
		];
	}

	public function authorize(): bool
	{
		return true;
	}
}
