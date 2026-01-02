<?php

declare( strict_types = 1 );

/**
 * Post Request for the CMS Framework Blog Module.
 *
 * This form request handles validation and authorization for post-related
 * HTTP requests, ensuring data integrity and security.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for post validation and authorization.
 *
 * Provides validation rules and authorization logic for post creation
 * and update operations with proper field validation.
 *
 * @since 1.0.0
 */
class PostRequest extends FormRequest
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
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique( 'posts', 'slug' )->ignore( $id ),
            ],
            'content'      => ['nullable', 'string'],
            'excerpt'      => ['nullable', 'string'],
            'author_id'    => ['required', 'integer', 'exists:users,id'],
            'status'       => ['required', 'string', 'in:draft,published,scheduled,private'],
            'published_at' => ['nullable', 'date'],
            'metadata'     => ['nullable', 'array'],
            'categories'   => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:post_categories,id'],
            'tags'         => ['nullable', 'array'],
            'tags.*'       => ['integer', 'exists:post_tags,id'],
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
            'title.required'     => __( 'The post title is required.' ),
            'slug.required'      => __( 'The post slug is required.' ),
            'slug.regex'         => __( 'The slug must be lowercase letters, numbers, and hyphens only.' ),
            'slug.unique'        => __( 'A post with this slug already exists.' ),
            'author_id.required' => __( 'The author is required.' ),
            'status.required'    => __( 'The post status is required.' ),
        ];
    }
}
