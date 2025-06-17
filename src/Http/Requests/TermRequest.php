<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TermRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['required', 'string', 'max:255', 'alpha_dash'],
            'taxonomy_id' => ['required', 'integer', 'exists:taxonomies,id'],
            'parent_id'   => ['nullable', 'integer', 'exists:terms,id'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
