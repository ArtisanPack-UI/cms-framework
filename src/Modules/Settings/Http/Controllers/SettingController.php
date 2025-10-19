<?php

/**
 * Setting Controller for the CMS Framework Users Module.
 *
 * This controller handles CRUD operations for setting including listing,
 * creating, showing, updating, and deleting setting records through API endpoints.
 *
 * @since   1.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;
use ArtisanPackUI\CMSFramework\Modules\Users\Http\Resources\SettingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * API controller for managing setting.
 *
 * Provides RESTful API endpoints for setting management operations
 * with proper validation, authorization, and resource transformation.
 *
 * @since 1.0.0
 */
class SettingController extends Controller
{
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
		$settings = Setting::with( 'permissions' )->paginate( 15 );

		return SettingResource::collection( $settings );
	}

	/**
	 * Store a newly created setting.
	 *
	 * Validates the incoming request data and creates a new setting with the
	 * provided information.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The HTTP request containing setting data.
	 *
	 * @return SettingResource The created setting resource with loaded permissions.
	 */
	public function store( Request $request ): SettingResource
	{
		$validated = $request->validate( [
											 'key'   => 'required|string|max:255|unique:settings',
											 'value' => 'required|string',
											 'type'  => 'required|string|max:255',
										 ] );

		$setting = Setting::create( $validated );
		$setting->load( 'permissions' );

		return new SettingResource( $setting );
	}

	/**
	 * Display the specified setting.
	 *
	 * Retrieves a single setting by ID with their associated permissions
	 * and returns it as a JSON resource.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the setting to retrieve.
	 *
	 * @return SettingResource The setting resource with loaded permissions.
	 */
	public function show( string|int $id ): SettingResource
	{
		$setting = Setting::with( 'permissions' )->findOrFail( $id );

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
	 * @param Request    $request The HTTP request containing updated setting data.
	 * @param string|int $id      The ID of the setting to update.
	 *
	 * @return SettingResource The updated setting resource with loaded permissions.
	 */
	public function update( Request $request, string|int $id ): SettingResource
	{
		$setting   = Setting::findOrFail( $id );
		$validated = $request->validate( [
											 'key'   => 'required|string|max:255|unique:settings,slug,' . $setting->id,
											 'value' => 'required|string',
											 'type'  => 'required|string|max:255',
										 ] );

		$setting->update( $validated );
		$setting->load( 'permissions' );

		return new SettingResource( $setting );
	}

	/**
	 * Remove the specified setting.
	 *
	 * Deletes a setting from the database and returns a successful response
	 * with no content.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $id The ID of the setting to delete.
	 *
	 * @return JsonResponse A JSON response with 204 status code.
	 */
	public function destroy( string|int $id ): JsonResponse
	{
		$setting = Setting::findOrFail( $id );
		$setting->delete();

		return response()->json( [], 204 );
	}
}