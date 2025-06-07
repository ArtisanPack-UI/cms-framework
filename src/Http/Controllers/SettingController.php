<?php
/**
 * Class SettingController
 *
 * Controller for managing settings in the application.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Http\Controllers
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\CMSFramework\Http\Requests\SettingRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\SettingResource;
use ArtisanPackUI\CMSFramework\Models\Setting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Class SettingController
 *
 * Handles HTTP requests related to settings management, including listing,
 * creating, viewing, updating, and deleting settings.
 *
 * @since 1.0.0
 */
class SettingController
{
	/**
	 * The AuthorizesRequests trait provides methods for authorizing user actions.
	 */
	use AuthorizesRequests;

	/**
	 * Display a listing of all settings.
	 *
	 * @since 1.0.0
	 *
	 * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection A collection of setting resources.
	 */
	public function index()
	{
		$this->authorize( 'viewAny', Setting::class );

		return SettingResource::collection( Setting::all() );
	}

	/**
	 * Store a newly created setting in the database.
	 *
	 * @since 1.0.0
	 *
	 * @param SettingRequest $request The validated request containing setting data.
	 * @return SettingResource The newly created setting resource.
	 */
	public function store( SettingRequest $request )
	{
		$this->authorize( 'create', Setting::class );

		return new SettingResource( Setting::create( $request->validated() ) );
	}

	/**
	 * Display the specified setting.
	 *
	 * @since 1.0.0
	 *
	 * @param Setting $setting The setting to display.
	 * @return SettingResource The specified setting resource.
	 */
	public function show( Setting $setting )
	{
		$this->authorize( 'view', $setting );

		return new SettingResource( $setting );
	}

	/**
	 * Update the specified setting in the database.
	 *
	 * @since 1.0.0
	 *
	 * @param SettingRequest $request The validated request containing updated setting data.
	 * @param Setting        $setting The setting to update.
	 * @return SettingResource The updated setting resource.
	 */
	public function update( SettingRequest $request, Setting $setting )
	{
		$this->authorize( 'update', $setting );

		$setting->update( $request->validated() );

		return new SettingResource( $setting );
	}

	/**
	 * Remove the specified setting from the database.
	 *
	 * @since 1.0.0
	 *
	 * @param Setting $setting The setting to delete.
	 * @return \Illuminate\Http\JsonResponse A JSON response indicating success.
	 */
	public function destroy( Setting $setting )
	{
		$this->authorize( 'delete', $setting );

		$setting->delete();

		return response()->json();
	}
}
