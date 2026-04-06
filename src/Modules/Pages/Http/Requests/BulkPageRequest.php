<?php

declare( strict_types=1 );

/**
 * Bulk Page Request for the CMS Framework Pages Module.
 *
 * This form request handles validation for bulk page operations,
 * ensuring the action and IDs are valid.
 *
 * @since 1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for bulk page operations.
 *
 * Validates the action type and array of page IDs for bulk operations.
 *
 * @since 1.1.0
 */
class BulkPageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @since 1.1.0
     *
     * @return bool True if the user is authorized, false otherwise.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the allowed actions for bulk page operations.
     *
     * @since 1.1.0
     *
     * @return array<int, string> The allowed action names.
     */
    public static function allowedActions(): array
    {
        return ['delete', 'publish', 'draft'];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @since 1.1.0
     *
     * @return array<string, mixed> The validation rules.
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in( self::allowedActions() )],
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['required', 'integer', 'exists:pages,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @since 1.1.0
     *
     * @return array<string, string> The custom error messages.
     */
    public function messages(): array
    {
        return [
            'action.required' => __( 'The bulk action is required.' ),
            'action.in'       => __( 'The selected action is invalid. Allowed actions: :values.', ['values' => implode( ', ', self::allowedActions() )] ),
            'ids.required'    => __( 'At least one page ID is required.' ),
            'ids.min'         => __( 'At least one page ID is required.' ),
            'ids.*.exists'    => __( 'One or more page IDs do not exist.' ),
        ];
    }
}
