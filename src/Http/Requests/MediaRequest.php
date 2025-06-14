<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MediaRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'is_decorative' => [ 'boolean' ],
        ];

        // Only require these fields when creating a new media item
        if ($this->isMethod('post')) {
            $rules['file'] = [ 'required', 'file' ];
            $rules['alt_text'] = [ 'required_if:is_decorative,false' ];
        }

        // For updates, only validate fields that are present
        if ($this->isMethod('put')) {
            $rules['alt_text'] = [ 'required_if:is_decorative,false' ];
        }

        return $rules;
    }

    public function authorize(): bool
    {
        return true;
    }
}
