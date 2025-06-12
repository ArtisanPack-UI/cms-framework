<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditLogRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'user_id'    => [ 'required', 'integer' ],
			'action'     => [ 'required' ],
			'message'    => [ 'required' ],
			'ip_address' => [ 'required' ],
			'user_agent' => [ 'required' ],
			'status'     => [ 'required' ],
		];
	}

	public function authorize(): bool
	{
		return true;
	}
}
