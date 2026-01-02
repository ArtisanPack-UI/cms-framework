<?php

declare( strict_types = 1 );

/**
 * Page Request for the CMS Framework Pages Module.
 *
 * This form request handles validation and authorization for page-related
 * HTTP requests, ensuring data integrity and security.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for page validation and authorization.
 *
 * Provides validation rules and authorization logic for page creation
 * and update operations with proper field validation.
 *
 * @since 1.0.0
 */
class PageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @since 1.0.0
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
     * @since 1.0.0
     *
     * @return array<string, mixed> The validation rules.
     */
    public function rules(): array
    {
        $id = $this->route( 'id' );

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug'  => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique( 'pages', 'slug' )->ignore( $id ),
            ],
            'content'      => ['nullable', 'string'],
            'excerpt'      => ['nullable', 'string'],
            'author_id'    => ['required', 'integer', 'exists:users,id'],
            'parent_id'    => ['nullable', 'integer', 'exists:pages,id'],
            'order'        => ['nullable', 'integer', 'min:0'],
            'template'     => ['nullable', 'string', 'max:255'],
            'status'       => ['required', 'string', 'in:draft,published,scheduled,private'],
            'published_at' => ['nullable', 'date'],
            'metadata'     => ['nullable', 'array'],
            'categories'   => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:page_categories,id'],
            'tags'         => ['nullable', 'array'],
            'tags.*'       => ['integer', 'exists:page_tags,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @since 1.0.0
     *
     * @return array<string, string> The custom error messages.
     */
    public function messages(): array
    {
        return [
            'title.required'     => __( 'The page title is required.' ),
            'slug.required'      => __( 'The page slug is required.' ),
            'slug.regex'         => __( 'The slug must be lowercase letters, numbers, and hyphens only.' ),
            'slug.unique'        => __( 'A page with this slug already exists.' ),
            'author_id.required' => __( 'The author is required.' ),
            'status.required'    => __( 'The page status is required.' ),
            'parent_id.exists'   => __( 'The selected parent page does not exist.' ),
        ];
    }
}
