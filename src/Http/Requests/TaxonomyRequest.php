<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaxonomyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'handle'        => ['required', 'string', 'max:50', 'alpha_dash', 'unique:taxonomies,handle'],
            'label'         => ['required', 'string', 'max:50'],
            'label_plural'  => ['required', 'string', 'max:50'],
            'content_types' => ['required', 'array'],
            'content_types.*' => ['string', 'exists:content_types,handle'],
            'hierarchical'  => ['required', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
