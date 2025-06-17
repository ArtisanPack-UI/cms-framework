<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:255'],
            'slug'         => ['required', 'string', 'max:255'],
            'content'      => ['nullable', 'string'],
            'type'         => ['required', 'string', 'max:50'],
            'status'       => ['required', 'string', 'in:draft,published,pending'],
            'author_id'    => ['required', 'integer', 'exists:users,id'],
            'parent_id'    => ['nullable', 'integer', 'exists:content,id'],
            'meta'         => ['nullable', 'array'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
