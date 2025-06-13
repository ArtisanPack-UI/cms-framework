<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PluginRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'slug'                  => [ 'required' ],
			'composer_package_name' => [ 'required' ],
			'directory_name'        => [ 'required' ],
			'plugin_class'          => [ 'required' ],
			'version'               => [ 'required' ],
			'is_active'             => [ 'boolean' ],
			'config'                => [ 'required' ],
		];
	}

	public function authorize(): bool
	{
		return true;
	}
}
