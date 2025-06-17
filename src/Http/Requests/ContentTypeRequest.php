<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContentTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'handle'       => ['required', 'string', 'max:50', 'alpha_dash', 'unique:content_types,handle'],
            'label'        => ['required', 'string', 'max:50'],
            'label_plural' => ['required', 'string', 'max:50'],
            'slug'         => ['required', 'string', 'max:50', 'alpha_dash'],
            'definition'   => ['required', 'array'],
            'definition.public' => ['boolean'],
            'definition.hierarchical' => ['boolean'],
            'definition.supports' => ['array'],
            'definition.fields' => ['array'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
