<?php

declare( strict_types=1 );

/**
 * Site Setting Controller.
 *
 * Thin façade over `SettingsManager` that exposes the five `site.*` settings
 * (title, tagline, url, logo_id, icon_id) under the WordPress
 * `/wp/v2/settings` envelope shape so visual-editor's `core/site-*` blocks
 * can read and write them through `apGetSetting()` / a single REST call.
 *
 * @since   2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Settings\Http\Requests\SiteSettingRequest;
use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * GET/PUT controller for the WP-shape site-meta envelope.
 *
 * @since 2.0.0
 */
#[Group( 'Settings', weight: 13 )]
class SiteSettingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Maps WP-shape envelope keys → underlying `site.*` setting keys.
     *
     * Kept as a constant so the GET and PUT paths stay in sync. Adding a
     * new field is a single-line change here.
     *
     * @since 2.0.0
     *
     * @var array<string, string>
     */
    protected const FIELD_MAP = [
        'title'       => 'site.title',
        'description' => 'site.tagline',
        'url'         => 'site.url',
        'site_logo'   => 'site.logo_id',
        'site_icon'   => 'site.icon_id',
    ];

    public function __construct( protected SettingsManager $settings )
    {
    }

    /**
     * Returns the current site-meta values.
     *
     * Reads each `site.*` setting through the manager so registered
     * defaults fill in for keys the host hasn't persisted yet.
     *
     * @since 2.0.0
     */
    public function show(): JsonResponse
    {
        $this->authorize( 'viewAny', Setting::class );

        return response()->json( $this->buildPayload() );
    }

    /**
     * Persists a partial WP-shape update.
     *
     * Only fields present in the validated payload are written; absent
     * fields are preserved. Sanitization runs through the per-key
     * sanitizer registered in `SettingsServiceProvider::registerSiteSettings()`.
     *
     * @since 2.0.0
     */
    public function update( SiteSettingRequest $request ): JsonResponse
    {
        $this->authorize( 'update', Setting::class );

        $validated = $request->validated();

        foreach ( self::FIELD_MAP as $envelopeKey => $settingKey ) {
            if ( array_key_exists( $envelopeKey, $validated ) ) {
                $this->settings->updateSetting( $settingKey, $validated[ $envelopeKey ] );
            }
        }

        // Return the freshly-built payload directly rather than calling
        // show(): the user has already cleared the `update` policy, and
        // hosts that bind separate capabilities for view-vs-update would
        // otherwise see a successful write turn into a 403 here.
        return response()->json( $this->buildPayload() );
    }

    /**
     * Reads the five site.* settings and shapes them as the WP envelope.
     *
     * Pure data access — does not authorize, so safe to call from any
     * action that has already cleared its own policy check.
     *
     * @since 2.0.0
     *
     * @return array<string, mixed>
     */
    protected function buildPayload(): array
    {
        $payload = [];

        foreach ( self::FIELD_MAP as $envelopeKey => $settingKey ) {
            $payload[ $envelopeKey ] = $this->settings->getSetting( $settingKey );
        }

        return $payload;
    }
}
