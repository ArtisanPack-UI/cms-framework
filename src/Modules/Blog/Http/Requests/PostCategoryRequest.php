<?php

/**
 * PostCategory Request for the CMS Framework Blog Module.
 *
 * This form request handles validation and authorization for post category-related
 * HTTP requests, ensuring data integrity and security.
 *
 * @since   2.0.0
 *
 * @package ArtisanPackUI\CMSFramework\Modules\Blog\Http\Requests
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for post category validation and authorization.
 *
 * Provides validation rules and authorization logic for post category creation
 * and update operations with proper field validation.
 *
 * @since 2.0.0
 */
class PostCategoryRequest extends FormRequest
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
        $id = $this->route('id');

        $parentIdRules = ['nullable', 'integer', 'exists:post_categories,id'];

        if ($id) {
            $parentIdRules[] = Rule::notIn([$id]);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('post_categories', 'slug')->ignore($id),
            ],
            'description' => ['nullable', 'string'],
            'parent_id' => $parentIdRules,
            'order' => ['integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
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
            'name.required' => __('The category name is required.'),
            'slug.required' => __('The category slug is required.'),
            'slug.regex' => __('The slug must be lowercase letters, numbers, and hyphens only.'),
            'slug.unique' => __('A category with this slug already exists.'),
        ];
    }
}
