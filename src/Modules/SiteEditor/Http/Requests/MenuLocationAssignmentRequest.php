<?php

/**
 * Menu Location Assignment Form Request
 *
 * Validates `PUT /api/v1/menu-locations/{location}` — assigning a menu to
 * a theme-declared location.
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @since 1.2.0
 */
class MenuLocationAssignmentRequest extends FormRequest
{
    /**
     * @since 1.2.0
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'menu' => [ 'required', 'integer', 'exists:menus,id' ],
        ];
    }

    /**
     * @since 1.2.0
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'menu.required' => __( 'The menu id is required.' ),
            'menu.exists'   => __( 'The menu id does not match an existing menu.' ),
        ];
    }
}
