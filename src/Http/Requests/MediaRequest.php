<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MediaRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'file_name'     => [ 'required' ],
			'mime_type'     => [ 'required' ],
			'path'          => [ 'required' ],
			'size'          => [ 'required', 'integer' ],
			'alt_text'      => [ 'required' ],
			'is_decorative' => [ 'boolean' ],
			'metadata'      => [ 'required' ],
		];
	}

	public function authorize(): bool
	{
		return true;
	}
}
