<?php
/**
 * Dashboard Widgets Manager
 *
 * Manages the registration and rendering of dashboard widgets.
 * Handles saving widget preferences to a user's profile.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\DashboardWidgets
 * @since      1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Features\DashboardWidgets;

use TorMorten\Eventy\Facades\Eventy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

// For generating unique IDs
use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets\DashboardWidget;

/**
 * Class for managing dashboard widgets
 *
 * Provides functionality to register dashboard widgets (types) and manage their
 * display and user-specific instance settings.
 *
 * @since 1.1.0
 */
class DashboardWidgetsManager
{
	/**
	 * Registered dashboard widget types.
	 * Keyed by widget type (class name or unique type string).
	 *
	 * @since 1.1.0
	 * @var DashboardWidget[]
	 */
	protected array $widgetTypes = [];

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	public function __CONSTRUCT()
	{
		// No direct dependency on SettingsManager here for user-specific settings
		// as we assume the User model handles its own settings.
	}

	/**
	 * Register a new dashboard widget type.
	 *
	 * Widgets should extend the base DashboardWidget class.
	 *
	 * @since 1.1.0
	 *
	 * @param DashboardWidget $widgetType An instance of a class extending DashboardWidget.
	 * @return void
	 */
	public function registerWidgetType( DashboardWidget $widgetType ): void
	{
		$widgetType->init(); // Initialize the widget to set its properties.
		$this->widgetTypes[ $widgetType->getType() ] = $widgetType;
	}

	/**
	 * Add a new instance of a widget type to a dashboard for the current user.
	 *
	 * @since 1.1.0
	 *
	 * @param string $widgetType      The type of widget to add (e.g., 'App\Widgets\WelcomeDashboardWidget').
	 * @param string $dashboardSlug   The slug of the dashboard to add the widget to.
	 * @param array  $initialSettings Optional. Initial settings for this widget instance, including 'order'.
	 * @return string|null The unique instance ID if successful, null otherwise.
	 */
	public function addWidgetInstance( string $widgetType, string $dashboardSlug, array $initialSettings = [] ): ?string
	{
		$widgetTypeInstance = $this->getWidgetType( $widgetType );

		if ( ! $widgetTypeInstance ) {
			return null; // Widget type not registered.
		}

		$user = Auth::user();
		if ( ! $user ) {
			return null;
		}

		$instances  = $this->getDashboardWidgetInstances( $dashboardSlug );
		$instanceId = (string) Str::uuid(); // Generate a unique ID for this instance.

		$defaultInstanceSettings = [ 'order' => 10 ]; // Default order for new instances.
		$settings                = array_merge( $defaultInstanceSettings, $initialSettings );
		$settings['order']       = $settings['order'] ?? 10; // Ensure order is set.

		$instances[] = [ // Add as new element, then re-save will re-index
			'id'       => $instanceId,
			'type'     => $widgetType,
			'settings' => $settings,
		];

		$this->saveDashboardWidgetInstances( $dashboardSlug, $instances );

		return $instanceId;
	}

	/**
	 * Get a specific registered widget type.
	 *
	 * @since 1.1.0
	 *
	 * @param string $type The unique type identifier of the widget.
	 * @return DashboardWidget|null The widget type instance, or null if not found.
	 */
	public function getWidgetType( string $type ): ?DashboardWidget
	{
		return $this->widgetTypes[ $type ] ?? null;
	}

	/**
	 * Get all widget instances for a specific dashboard and user.
	 *
	 * This returns an array of widget instance data, including their unique IDs,
	 * type, and user-specific settings (including order).
	 *
	 * @since 1.1.0
	 *
	 * @param string $dashboardSlug The slug of the specific dashboard.
	 * @return array An array of widget instance data.
	 *                              Each element is an associative array with 'id', 'type', and 'settings'.
	 */
	public function getDashboardWidgetInstances( string $dashboardSlug ): array
	{
		$user = Auth::user();
		if ( ! $user ) {
			return [];
		}

		$settingKey       = 'dashboard_widgets_instances_' . $dashboardSlug;
		$instances        = $user->getSetting( $settingKey, [] );
		$widgetTypes      = $this->getRegisteredWidgetTypes();
		$orderedInstances = [];

		// Reconstruct instances with full settings and filter by registered types
		foreach ( $instances as $instanceId => $instanceData ) {
			$widgetType = $widgetTypes[ $instanceData['type'] ] ?? null;
			if ( $widgetType ) {
				$instanceData['id'] = $instanceId; // Ensure ID is part of data
				// Merge with default settings to ensure 'order' and other required keys exist.
				$defaultInstanceSettings  = [ 'order' => 10 ]; // Default order for new instances
				$instanceData['settings'] = array_merge( $defaultInstanceSettings, $instanceData['settings'] ?? [] );
				$orderedInstances[]       = $instanceData;
			}
		}

		// Sort by 'order' from settings
		usort( $orderedInstances, function ( $a, $b ) {
			return $a['settings']['order'] <=> $b['settings']['order'];
		} );

		return $orderedInstances;
	}

	/**
	 * Get all registered dashboard widget types.
	 *
	 * @since 1.1.0
	 * @return DashboardWidget[] An array of registered widget type instances.
	 */
	public function getRegisteredWidgetTypes(): array
	{
		/**
		 * Filters the registered dashboard widget types.
		 *
		 * Allows other modules to add, remove, or modify dashboard widget types.
		 *
		 * @since 1.1.0
		 *
		 * @param DashboardWidget[] $widgetTypes The array of registered widget type instances.
		 */
		return Eventy::filter( 'ap.cms.dashboard.widget_types', $this->widgetTypes );
	}

	/**
	 * Save all widget instances for a specific dashboard and user.
	 *
	 * This overwrites the existing instances for the given dashboard.
	 *
	 * @since 1.1.0
	 *
	 * @param string $dashboardSlug The slug of the specific dashboard.
	 * @param array  $instances     An array of widget instance data to save.
	 *                              Each element must have 'id', 'type', and 'settings' (including 'order').
	 * @return void
	 */
	public function saveDashboardWidgetInstances( string $dashboardSlug, array $instances ): void
	{
		$user = Auth::user();
		if ( ! $user ) {
			return;
		}

		$settingKey = 'dashboard_widgets_instances_' . $dashboardSlug;
		$dataToSave = [];
		foreach ( $instances as $instance ) {
			if ( isset( $instance['id'], $instance['type'], $instance['settings']['order'] ) ) {
				$dataToSave[ $instance['id'] ] = [
					'type'     => $instance['type'],
					'settings' => $instance['settings'],
				];
			}
		}
		$user->setSetting( $settingKey, $dataToSave );
	}

	/**
	 * Remove a widget instance from a dashboard for the current user.
	 *
	 * @since 1.1.0
	 *
	 * @param string $instanceId    The unique ID of the widget instance to remove.
	 * @param string $dashboardSlug The slug of the dashboard from which to remove the widget.
	 * @return bool True if removed, false otherwise.
	 */
	public function removeWidgetInstance( string $instanceId, string $dashboardSlug ): bool
	{
		$user = Auth::user();
		if ( ! $user ) {
			return false;
		}

		$instances     = $this->getDashboardWidgetInstances( $dashboardSlug );
		$originalCount = count( $instances );

		$updatedInstances = [];
		foreach ( $instances as $instance ) {
			if ( $instance['id'] !== $instanceId ) {
				$updatedInstances[] = $instance;
			}
		}

		if ( count( $updatedInstances ) < $originalCount ) {
			$this->saveDashboardWidgetInstances( $dashboardSlug, $updatedInstances );
			return true;
		}

		return false;
	}

	/**
	 * Get user-specific settings for a single widget instance.
	 *
	 * @since 1.1.0
	 *
	 * @param string      $instanceId    The unique ID of the widget instance.
	 * @param string|null $dashboardSlug Optional. The slug of the specific dashboard. Default null (main dashboard).
	 * @param mixed       $default       Default value if the widget instance settings don't exist. Default empty array.
	 * @return array An associative array of settings for the specific widget instance, including 'order'.
	 */
	public function getUserWidgetInstanceSettings( string $instanceId, ?string $dashboardSlug = null, mixed $default = [] ): array
	{
		$user = Auth::user();
		if ( ! $user ) {
			return (array) $default;
		}

		$dashboardSlug = $dashboardSlug ?? 'main';
		$instances     = $this->getDashboardWidgetInstances( $dashboardSlug );

		foreach ( $instances as $instance ) {
			if ( $instance['id'] === $instanceId ) {
				$defaultSettings = [ 'order' => 10 ]; // Default order if not in saved settings.
				return array_merge( $defaultSettings, $instance['settings'] ?? [] );
			}
		}

		// Return default if instance not found, ensuring 'order' is always present.
		return array_merge( [ 'order' => 10 ], (array) $default );
	}

	/**
	 * Save user-specific settings for a single widget instance.
	 *
	 * This updates the settings for a *specific instance* within the user's dashboard configuration.
	 *
	 * @since 1.1.0
	 *
	 * @param string      $instanceId    The unique ID of the widget instance.
	 * @param array       $settings      An associative array of settings for the widget instance. This *must* include
	 *                                   an 'order' key.
	 * @param string|null $dashboardSlug Optional. The slug of the specific dashboard. Default null (main dashboard).
	 * @return void
	 */
	public function saveUserWidgetInstanceSettings( string $instanceId, array $settings, ?string $dashboardSlug = null ): void
	{
		$user = Auth::user();
		if ( ! $user ) {
			return;
		}

		if ( ! isset( $settings['order'] ) ) {
			// Ensure 'order' is always present. Could also throw an exception.
			$settings['order'] = 10;
		}

		$dashboardSlug = $dashboardSlug ?? 'main';
		$instances     = $this->getDashboardWidgetInstances( $dashboardSlug );

		$found = false;
		foreach ( $instances as &$instance ) { // Pass by reference to modify in place.
			if ( $instance['id'] === $instanceId ) {
				$instance['settings'] = $settings;
				$found                = true;
				break;
			}
		}

		if ( $found ) {
			$this->saveDashboardWidgetInstances( $dashboardSlug, $instances );
		}
	}

	/**
	 * Render a specific widget instance.
	 *
	 * @since 1.1.0
	 *
	 * @param string $instanceId    The unique ID of the widget instance to render.
	 * @param string $dashboardSlug The slug of the dashboard where the widget is located.
	 * @param array  $data          Optional. Data to pass to the widget view/component. Default empty array.
	 * @return string The rendered HTML of the widget instance.
	 */
	public function renderWidgetInstance( string $instanceId, string $dashboardSlug, array $data = [] ): string
	{
		$instancesData = $this->getDashboardWidgetInstances( $dashboardSlug );
		$instanceData  = null;

		foreach ( $instancesData as $instance ) {
			if ( $instance['id'] === $instanceId ) {
				$instanceData = $instance;
				break;
			}
		}

		if ( ! $instanceData ) {
			return ''; // Instance not found.
		}

		$widgetType = $this->getWidgetType( $instanceData['type'] );

		if ( ! $widgetType ) {
			return ''; // Widget type not registered.
		}

		// Merge widget instance specific settings from the user with any provided data
		$widgetData = array_merge( $instanceData['settings'], $data );

		// Delegate rendering to the widget type object, passing the instance ID.
		return $widgetType->render( $instanceId, $widgetData );
	}
}