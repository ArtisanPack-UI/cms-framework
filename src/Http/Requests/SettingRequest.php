<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'name'     => [ 'required' ],
			'value'    => [ 'nullable' ],
			'category' => [ 'nullable' ],
		];
	}

	public function authorize(): bool
	{
		return true;
	}
}
