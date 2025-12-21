<?php

declare( strict_types = 1 );

/**
 * PageTag Request for the CMS Framework Pages Module.
 *
 * This form request handles validation and authorization for page tag-related
 * HTTP requests, ensuring data integrity and security.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for page tag validation and authorization.
 *
 * Provides validation rules and authorization logic for page tag creation
 * and update operations with proper field validation.
 *
 * @since 1.0.0
 */
class PageTagRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique( 'page_tags', 'slug' )->ignore( $id ),
            ],
            'description' => ['nullable', 'string'],
            'order'       => ['integer', 'min:0'],
            'metadata'    => ['nullable', 'array'],
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
            'name.required' => __( 'The tag name is required.' ),
            'slug.required' => __( 'The tag slug is required.' ),
            'slug.regex'    => __( 'The slug must be lowercase letters, numbers, and hyphens only.' ),
            'slug.unique'   => __( 'A tag with this slug already exists.' ),
        ];
    }
}
