<?php

/**
 * Menu Form Request
 *
 * Validates store/update payloads for `/api/v1/menus`.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SiteEditorAbilities;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SlugValidator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @since 2.0.0
 */
class MenuRequest extends FormRequest
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
        $slugPresence = $this->isMethod( 'post' ) ? 'required' : 'sometimes';

        return [
            'slug'           => [$slugPresence, 'string', 'max:255', 'regex:' . SlugValidator::PATTERN],
            'name'           => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'auto_add_pages' => ['nullable', 'boolean'],
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
            'slug.required' => __( 'The menu slug is required.' ),
            'slug.regex'    => __( 'The slug must be lowercase letters, numbers, and hyphens only.' ),
            'name.required' => __( 'The menu name is required.' ),
        ];
    }
}
