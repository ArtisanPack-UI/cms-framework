<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContentRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'title'        => ['required', 'string', 'max:255'],
            'content'      => ['nullable', 'string'],
            'parent_id'    => ['nullable', 'integer', 'exists:content,id'],
            'meta'         => ['nullable', 'array'],
            'published_at' => ['nullable', 'date'],
            'terms'        => ['nullable', 'array'],
            'terms.*'      => ['exists:terms,id'],
        ];

        // If this is a create request (not an update), these fields are required
        if (!$this->isMethod('PUT') && !$this->isMethod('PATCH')) {
            $rules['slug'] = ['required', 'string', 'max:255'];
            $rules['type'] = ['required', 'string', 'max:50'];
            $rules['status'] = ['required', 'string', 'in:draft,published,pending'];
            $rules['author_id'] = ['required', 'integer', 'exists:users,id'];
        } else {
            // For updates, these fields are optional
            $rules['slug'] = ['sometimes', 'string', 'max:255'];
            $rules['type'] = ['sometimes', 'string', 'max:50'];
            $rules['status'] = ['sometimes', 'string', 'in:draft,published,pending'];
            $rules['author_id'] = ['sometimes', 'integer', 'exists:users,id'];
        }

        return $rules;
    }

    public function authorize(): bool
    {
        return true;
    }
}
