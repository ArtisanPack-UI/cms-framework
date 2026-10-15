<?php

/**
 * Menu Location Assignment Form Request
 *
 * Validates `PUT /api/v1/menu-locations/{location}` — assigning a menu to
 * a theme-declared location.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SiteEditorAbilities;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @since 2.0.0
 */
class MenuLocationAssignmentRequest extends FormRequest
{
    /**
     * Require the Site Editor ability for this request's group. The
     * route middleware checks it too; this keeps the request safe when
     * a host reuses it on its own route.
     *
     * @since 2.0.0
     * @since 2.15.0 Requires the group ability {@see SiteEditorAbilities::allows()} checks instead of allowing every user.
     */
    public function authorize(): bool
    {
        return SiteEditorAbilities::allows( $this->user(), SiteEditorAbilities::STRUCTURE );
    }

    /**
     * @since 2.0.0
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'menu' => ['required', 'integer', 'exists:menus,id'],
        ];
    }

    /**
     * @since 2.0.0
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
