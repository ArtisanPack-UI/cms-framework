<?php

/**
 * Global Styles Form Request
 *
 * Loose top-level validation for the WP `theme.json` `settings` + `styles`
 * shapes. Unknown root keys reject; nested validation is intentionally
 * deferred to V1.1 — the WP schema is large and editor-side adapters
 * already validate before submission.
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
class GlobalStylesRequest extends FormRequest
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
            'settings'  => ['nullable', 'array'],
            'styles'    => ['nullable', 'array'],
            'variation' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'title'     => ['nullable', 'string', 'max:255'],
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
            'variation.regex' => __( 'The variation slug must be alphanumeric, hyphens, or underscores.' ),
        ];
    }
}
