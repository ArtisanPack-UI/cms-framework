<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Content Request.
 *
 * Handles validation and authorization for content operations.
 * Manages different validation rules for creation and update operations.
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Requests
 * @since      1.1.0
 */
class ContentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Applies different validation rules based on whether this is a create
     * or update request. For create requests, slug, type, status, and author_id
     * are required. For update requests, these fields are optional.
     *
     * @since 1.1.0
     *
     * @return array<string, mixed> Array of validation rules.
     */
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

    /**
     * Determine if the user is authorized to make this request.
     *
     * This method always returns true, allowing all authenticated users
     * to perform content operations. Actual authorization is handled
     * in the ContentController using policies.
     *
     * @since 1.1.0
     *
     * @return bool Always returns true.
     */
    public function authorize(): bool
    {
        return true;
    }
}
