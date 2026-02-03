<?php

declare( strict_types = 1 );

/**
 * CustomField Request for the CMS Framework ContentTypes Module.
 *
 * This form request handles validation and authorization for custom field-related
 * HTTP requests, ensuring data integrity and security.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for custom field validation and authorization.
 *
 * Provides validation rules and authorization logic for custom field creation
 * and update operations with proper field validation.
 *
 * @since 1.0.0
 */
class CustomFieldRequest extends FormRequest
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
        $id = $this->route( 'id' );

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'key' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique( 'custom_fields', 'key' )->ignore( $id ),
            ],
            'type' => [
                'required',
                'string',
                'in:text,textarea,number,select,checkbox,radio,boolean,date,datetime,time,email,url,tel,color,file,image',
            ],
            'column_type' => [
                'required',
                'string',
                'in:string,text,integer,bigInteger,decimal,float,double,boolean,date,dateTime,time,json,binary',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'content_types' => [
                'required',
                'array',
                'min:1',
            ],
            'content_types.*' => [
                'string',
            ],
            'options' => [
                'nullable',
                'array',
            ],
            'order' => [
                'integer',
                'min:0',
            ],
            'required' => [
                'boolean',
            ],
            'default_value' => [
                'nullable',
                'string',
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
            'name.required'          => __( 'The custom field name is required.' ),
            'key.required'           => __( 'The custom field key is required.' ),
            'key.regex'              => __( 'The key must be lowercase letters, numbers, and underscores only.' ),
            'key.unique'             => __( 'A custom field with this key already exists.' ),
            'type.required'          => __( 'The field type is required.' ),
            'column_type.required'   => __( 'The column type is required.' ),
            'content_types.required' => __( 'At least one content type must be selected.' ),
            'content_types.min'      => __( 'At least one content type must be selected.' ),
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
            'key'           => __( 'key' ),
            'type'          => __( 'type' ),
            'column_type'   => __( 'column type' ),
            'description'   => __( 'description' ),
            'content_types' => __( 'content types' ),
            'options'       => __( 'options' ),
            'order'         => __( 'order' ),
            'required'      => __( 'required' ),
            'default_value' => __( 'default value' ),
        ];
    }
}
