<?php

declare( strict_types=1 );

/**
 * Setting Controller for the CMS Framework Settings Module.
 *
 * This controller handles CRUD operations for setting including listing,
 * creating, showing, updating, and deleting setting records through API endpoints.
 *
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Settings\Http\Requests\RegisteredSettingsRequest;
use ArtisanPackUI\CMSFramework\Modules\Settings\Http\Requests\SettingRequest;
use ArtisanPackUI\CMSFramework\Modules\Settings\Http\Resources\SettingResource;
use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * API controller for managing setting.
 *
 * Provides RESTful API endpoints for setting management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 1.0.0
 */
#[Group( 'Settings', weight: 13 )]
class SettingController extends Controller
{
    use AuthorizesRequests;

    public function __construct( protected SettingsManager $settings )
    {
    }

    /**
     * Display a listing of settings.
     *
     * Retrieves a paginated list of settings with their associated permissions
     * and returns them as a JSON resource collection.
     *
     * @since 1.0.0
     *
     * @return AnonymousResourceCollection The paginated collection of setting resources.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize( 'viewAny', Setting::class );

        $settings = Setting::paginate( 15 );

        return SettingResource::collection( $settings );
    }

    /**
     * Bulk-update one or more registered settings by key.
     *
     * Each supplied value is routed through `SettingsManager::updateSetting()`
     * so the per-key sanitizer callback and registered `SettingType` always
     * apply — unlike {@see store()}/{@see update()}, which write to the model
     * directly and bypass both. Use this path for saving registered settings
     * (e.g. an admin settings page); the generic ID/key CRUD remains for
     * ad-hoc, unregistered keys.
     *
     * Unknown keys are rejected by {@see RegisteredSettingsRequest}, so every
     * key reaching this method is guaranteed to be registered. The response
     * echoes the freshly-read values so callers observe exactly what was
     * persisted (sanitized and cast).
     *
     * @since 2.0.0
     *
     * @param  RegisteredSettingsRequest  $request  The request carrying the `settings` key/value map.
     *
     * @return JsonResponse The sanitized, persisted values keyed by setting key.
     */
    public function bulkUpdate( RegisteredSettingsRequest $request ): JsonResponse
    {
        $this->authorize( 'update', Setting::class );

        /** @var array<string, mixed> $values */
        $values  = $request->validated()['settings'];
        $applied = [];

        // Persist the whole group atomically: if any key fails, no partial
        // write survives and the caller can safely retry the same payload.
        DB::transaction( function () use ( $values, &$applied ): void {
            foreach ( $values as $key => $value ) {
                $this->settings->updateSetting( $key, $value );
                $applied[ $key ] = $this->settings->getSetting( $key );
            }
        } );

        return response()->json( ['settings' => $applied] );
    }

    /**
     * Store a newly created setting.
     *
     * Validates the incoming request data and creates a new setting with the
     * provided information. Returns the created resource with a 201 status code.
     *
     * @since 1.0.0
     * @since 1.0.0 Adjusted return type to JsonResponse to match implementation.
     *
     * @param  SettingRequest  $request  The HTTP request containing setting data.
     *
     * @return JsonResponse The JSON response containing the created setting resource.
     */
    public function store( SettingRequest $request ): JsonResponse // Correct return type hint
    {
        $this->authorize( 'create', Setting::class );

        $validated = $request->validated();
        $setting   = Setting::create( $validated );

        // Return JsonResponse explicitly with 201 status
        return response()->json( new SettingResource( $setting ), 201 );
    }

    /**
     * Display the specified setting.
     *
     * Retrieves a single setting by ID with their associated permissions
     * and returns it as a JSON resource.
     *
     * @since 1.0.0
     *
     * @param  int|string  $id  The ID of the setting to retrieve.
     *
     * @return SettingResource The setting resource with loaded permissions.
     */
    public function show( string|int $id ): SettingResource
    {
        $setting = Setting::findOrFail( $id );
        $this->authorize( 'view', $setting );

        return new SettingResource( $setting );
    }

    /**
     * Update the specified setting.
     *
     * Validates the incoming request data and updates the setting with the
     * provided information. Only provided fields are updated (partial updates).
     *
     * @since 1.0.0
     *
     * @param  SettingRequest  $request  The HTTP request containing updated setting data.
     * @param  int|string  $id  The ID of the setting to update.
     *
     * @return SettingResource The updated setting resource with loaded permissions.
     */
    public function update( SettingRequest $request, string|int $id ): SettingResource
    {
        $setting = Setting::findOrFail( $id );
        $this->authorize( 'update', $setting );
        $validated = $request->validated();
        $setting->update( $validated );

        return new SettingResource( $setting );
    }

    /**
     * Remove the specified setting.
     *
     * Deletes a setting from the database and returns a successful response
     * with no content.
     *
     * @since 1.0.0
     * @since 1.0.0 Corrected return type hint to Response.
     *
     * @param  int|string  $id  The ID (key) of the setting to delete.
     *
     * @return Response A response with 204 status code.
     */
    public function destroy( string|int $id ): Response // Correct return type hint
    {
        $setting = Setting::findOrFail( $id );
        $this->authorize( 'delete', $setting );
        $setting->delete();

        return response()->noContent();
    }
}
