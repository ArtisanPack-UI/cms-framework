<?php

declare( strict_types = 1 );

/**
 * ContentType Request for the CMS Framework ContentTypes Module.
 *
 * This form request handles validation and authorization for content type-related
 * HTTP requests, ensuring data integrity and security.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for content type validation and authorization.
 *
 * Provides validation rules and authorization logic for content type creation
 * and update operations with proper field validation.
 *
 * @since 1.0.0
 */
class ContentTypeRequest extends FormRequest
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
        // Authorization is handled by policies, so we return true here
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
        $slug = $this->route( 'slug' );

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique( 'content_types', 'slug' )->ignore( $slug, 'slug' ),
            ],
            'table_name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
            ],
            'model_class' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'hierarchical' => [
                'boolean',
            ],
            'has_archive' => [
                'boolean',
            ],
            'archive_slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],
            'supports' => [
                'nullable',
                'array',
            ],
            'supports.*' => [
                'string',
                'in:title,content,excerpt,featured_image,author,thumbnail,comments,revisions,page_attributes,custom_fields',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
            'public' => [
                'boolean',
            ],
            'show_in_admin' => [
                'boolean',
            ],
            'icon' => [
                'nullable',
                'string',
                'max:255',
            ],
            'menu_position' => [
                'nullable',
                'integer',
            ],
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
            'name.required'        => __( 'The content type name is required.' ),
            'slug.required'        => __( 'The content type slug is required.' ),
            'slug.regex'           => __( 'The slug must be lowercase letters, numbers, and hyphens only.' ),
            'slug.unique'          => __( 'A content type with this slug already exists.' ),
            'table_name.required'  => __( 'The table name is required.' ),
            'table_name.regex'     => __( 'The table name must be lowercase letters, numbers, and underscores only.' ),
            'model_class.required' => __( 'The model class is required.' ),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @since 1.0.0
     *
     * @return array<string, string> The custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'name'          => __( 'name' ),
            'slug'          => __( 'slug' ),
            'table_name'    => __( 'table name' ),
            'model_class'   => __( 'model class' ),
            'description'   => __( 'description' ),
            'hierarchical'  => __( 'hierarchical' ),
            'has_archive'   => __( 'has archive' ),
            'archive_slug'  => __( 'archive slug' ),
            'supports'      => __( 'supports' ),
            'metadata'      => __( 'metadata' ),
            'public'        => __( 'public' ),
            'show_in_admin' => __( 'show in admin' ),
            'icon'          => __( 'icon' ),
            'menu_position' => __( 'menu position' ),
        ];
    }
}
