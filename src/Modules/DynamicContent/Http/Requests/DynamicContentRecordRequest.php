<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DynamicContentRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label'    => [ 'nullable', 'string', 'max:255' ],
            'order'    => [ 'nullable', 'integer' ],
            'values'   => [ 'array' ],
            'values.*' => [ 'nullable' ],
        ];
    }
}
