<?php

namespace ArtisanPackUI\CMSFramework\Http\Requests;

use ArtisanPackUI\CMSFramework\Http\Utilities\InputSanitizer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Content Request.
 *
 * Handles validation and authorization for content operations.
 * Manages different validation rules for creation and update operations.
 *
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
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'content' => ['nullable', 'string', 'max:2000000'], // 2MB max content
            'parent_id' => ['nullable', 'integer', 'min:1', 'exists:content,id'],
            'meta' => ['nullable', 'array', 'max:50'], // Limit meta array size
            'meta.*' => ['string', 'max:1000'], // Limit individual meta values
            'published_at' => ['nullable', 'date', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'terms' => ['nullable', 'array', 'max:20'], // Reasonable limit on terms
            'terms.*' => ['integer', 'min:1', 'exists:terms,id'],
        ];

        // If this is a create request (not an update), these fields are required
        if (! $this->isMethod('PUT') && ! $this->isMethod('PATCH')) {
            $rules['slug'] = ['required', 'string', 'min:1', 'max:255', 'regex:/^[a-z0-9\-_]+$/'];
            $rules['type'] = ['required', 'string', 'min:1', 'max:50', 'alpha_dash'];
            $rules['status'] = ['required', 'string', 'in:draft,published,pending,private'];
            $rules['author_id'] = ['required', 'integer', 'min:1', 'exists:users,id'];
        } else {
            // For updates, these fields are optional but must follow same rules if present
            $rules['slug'] = ['sometimes', 'string', 'min:1', 'max:255', 'regex:/^[a-z0-9\-_]+$/'];
            $rules['type'] = ['sometimes', 'string', 'min:1', 'max:50', 'alpha_dash'];
            $rules['status'] = ['sometimes', 'string', 'in:draft,published,pending,private'];
            $rules['author_id'] = ['sometimes', 'integer', 'min:1', 'exists:users,id'];
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     *
     * Sanitizes input data before validation to prevent XSS attacks and ensure data integrity.
     * Applies HTML purification to content fields and text sanitization to other fields.
     *
     * @since 1.1.0
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();

        // Sanitize title (strip HTML, prevent XSS)
        if (isset($input['title'])) {
            $input['title'] = InputSanitizer::sanitizeText($input['title'], 255);
        }

        // Sanitize content with HTML purification (allow safe HTML)
        if (isset($input['content'])) {
            $input['content'] = InputSanitizer::sanitizeHtml($input['content']);
        }

        // Sanitize slug (ensure it's URL-safe)
        if (isset($input['slug'])) {
            $slug = InputSanitizer::sanitizeText($input['slug'], 255);
            // Convert to lowercase and replace spaces/special chars with hyphens
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9\-_]/', '-', $slug));
            $slug = preg_replace('/-+/', '-', $slug); // Remove multiple consecutive hyphens
            $input['slug'] = trim($slug, '-');
        }

        // Sanitize type field
        if (isset($input['type'])) {
            $input['type'] = InputSanitizer::sanitizeText($input['type'], 50);
            $input['type'] = strtolower($input['type']);
        }

        // Sanitize meta array
        if (isset($input['meta']) && is_array($input['meta'])) {
            $input['meta'] = InputSanitizer::sanitizeArray($input['meta'], 'text');
        }

        // Ensure terms is an array of integers
        if (isset($input['terms']) && is_array($input['terms'])) {
            $input['terms'] = array_map(function ($term) {
                return InputSanitizer::sanitizeInteger($term, 1);
            }, $input['terms']);
            // Remove any zero or negative values
            $input['terms'] = array_filter($input['terms'], function ($term) {
                return $term > 0;
            });
        }

        // Sanitize parent_id
        if (isset($input['parent_id'])) {
            $input['parent_id'] = InputSanitizer::sanitizeInteger($input['parent_id'], 1);
        }

        // Sanitize author_id
        if (isset($input['author_id'])) {
            $input['author_id'] = InputSanitizer::sanitizeInteger($input['author_id'], 1);
        }

        $this->replace($input);
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
