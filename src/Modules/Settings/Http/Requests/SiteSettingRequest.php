<?php

declare(strict_types=1);

/**
 * Site Setting Request.
 *
 * Validates partial-update payloads for the WP-shape `/api/v1/settings/site`
 * endpoint. Each field is optional so the editor (and admin UI) can PATCH a
 * single attribute without re-supplying the rest.
 *
 * @since   1.2.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for site-meta updates.
 *
 * The body uses the WordPress `/wp/v2/settings` envelope (title,
 * description, url, site_logo, site_icon); mapping back to the
 * underlying `site.*` setting keys is the controller's job.
 *
 * @since 1.2.0
 */
class SiteSettingRequest extends FormRequest
{
    /**
     * Authorization is delegated to the controller's policy check.
     *
     * @since 1.2.0
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for the WP-shape body.
     *
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:255'],
            'url'         => ['sometimes', 'url', 'max:2048'],
            'site_logo'   => ['sometimes', 'nullable', 'integer'],
            'site_icon'   => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * Friendlier attribute names for validator errors.
     *
     * @since 1.2.0
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title'       => __('site title'),
            'description' => __('site description'),
            'url'         => __('site URL'),
            'site_logo'   => __('site logo'),
            'site_icon'   => __('site icon'),
        ];
    }
}
