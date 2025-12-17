<?php

/**
 * Taxonomy Request for the CMS Framework ContentTypes Module.
 *
 * This form request handles validation and authorization for taxonomy-related
 * HTTP requests, ensuring data integrity and security.
 *
 * @since   2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for taxonomy validation and authorization.
 *
 * Provides validation rules and authorization logic for taxonomy creation
 * and update operations with proper field validation.
 *
 * @since 2.0.0
 */
class TaxonomyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @since 2.0.0
     *
     * @return bool True if the user is authorized, false otherwise.
     */
    public function authorize(): bool
    {
        // Authorization is handled by policies, so we return true here
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @since 2.0.0
     *
     * @return array<string, mixed> The validation rules.
     */
    public function rules(): array
    {
        $slug = $this->route('slug');

        $slugRules = [
            'required',
            'string',
            'max:255',
            'regex:/^[a-z0-9_]+$/',
        ];

        $uniqueRule = Rule::unique('taxonomies', 'slug');
        if ($slug) {
            $uniqueRule->ignore($slug, 'slug');
        }
        $slugRules[] = $uniqueRule;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'slug' => $slugRules,
            'content_type_slug' => [
                'required',
                'string',
                'max:255',
                'exists:content_types,slug',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'hierarchical' => [
                'boolean',
            ],
            'show_in_admin' => [
                'boolean',
            ],
            'rest_base' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @since 2.0.0
     *
     * @return array<string, string> The custom error messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => __('The taxonomy name is required.'),
            'slug.required' => __('The taxonomy slug is required.'),
            'slug.regex' => __('The slug must be lowercase letters, numbers, and underscores only.'),
            'slug.unique' => __('A taxonomy with this slug already exists.'),
            'content_type_slug.required' => __('The content type is required.'),
            'content_type_slug.exists' => __('The selected content type does not exist.'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @since 2.0.0
     *
     * @return array<string, string> The custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'name' => __('name'),
            'slug' => __('slug'),
            'content_type_slug' => __('content type'),
            'description' => __('description'),
            'hierarchical' => __('hierarchical'),
            'show_in_admin' => __('show in admin'),
            'rest_base' => __('REST base'),
            'metadata' => __('metadata'),
        ];
    }
}
