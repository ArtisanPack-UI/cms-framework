<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MediaCategoryRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'name' => [ 'required' ],
			'slug' => [ 'required', 'unique:media_categories,slug' . ($this->mediaCategory ? ',' . $this->mediaCategory->id : '') ],
		];
	}

	public function authorize(): bool
	{
		return true;
	}
}
