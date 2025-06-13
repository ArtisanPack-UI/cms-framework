<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MediaTagRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'name' => [ 'required' ],
			'slug' => [ 'required' ],
		];
	}

	public function authorize(): bool
	{
		return true;
	}
}
