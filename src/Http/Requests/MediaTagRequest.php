<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MediaTagRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'name' => [ 'required' ],
			'slug' => [ 'required', 'unique:media_tags,slug' . ($this->mediaTag ? ',' . $this->mediaTag->id : '') ],
		];
	}

	public function authorize(): bool
	{
		return true;
	}
}
