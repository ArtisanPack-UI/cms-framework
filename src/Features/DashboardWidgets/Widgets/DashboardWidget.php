<?php
/**
 * Abstract Dashboard Widget Class
 *
 * Provides a base structure and common functionalities for all dashboard widgets
 * within the ArtisanPack UI CMS Framework.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets
 * @since      1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Features\DashboardWidgets\Widgets;

use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\DashboardWidgetsManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;

// Import Livewire if using Livewire components

/**
 * Abstract class for a dashboard widget.
 *
 * Child classes must implement the logic for displaying the widget content.
 * Provides common properties and methods for widget management.
 *
 * @since 1.1.0
 */
abstract class DashboardWidget
{
	/**
	 * The unique type/identifier for this widget class.
	 * This remains constant for all instances of this widget.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	protected string $type;

	/**
	 * The display name of the widget.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	protected string $name;

	/**
	 * The slug for the widget type (e.g., 'welcome-widget').
	 *
	 * @since 1.1.0
	 * @var string
	 */
	protected string $slug;

	/**
	 * Optional description for the widget.
	 *
	 * @since 1.1.0
	 * @var string|null
	 */
	protected ?string $description = null;

	/**
	 * The Blade view path for the widget's content.
	 *
	 * @since 1.1.0
	 * @var string|null
	 */
	protected ?string $view = null;

	/**
	 * The Livewire component class for the widget's content.
	 *
	 * @since 1.1.0
	 * @var string|null
	 */
	protected ?string $component = null;

	/**
	 * Retrieves the widget's type identifier.
	 *
	 * This is the class-level identifier, not the instance ID.
	 *
	 * @since 1.1.0
	 * @return string The widget type.
	 */
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * Retrieves the widget's display name.
	 *
	 * @since 1.1.0
	 * @return string The widget name.
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Retrieves the widget's slug.
	 *
	 * @since 1.1.0
	 * @return string The widget slug.
	 */
	public function getSlug(): string
	{
		return $this->slug;
	}

	/**
	 * Retrieves the widget's description.
	 *
	 * @since 1.1.0
	 * @return string|null The widget description.
	 */
	public function getDescription(): ?string
	{
		return $this->description;
	}

	/**
	 * Get current user's settings for a specific instance of this widget.
	 *
	 * @since 1.1.0
	 *
	 * @param string      $instanceId    The unique ID of the widget instance.
	 * @param string|null $dashboardSlug Optional. The slug of the specific dashboard. Default null (main dashboard).
	 * @param mixed       $default       Default value if the widget settings don't exist. Default empty array.
	 * @return array An associative array of settings for this widget instance.
	 */
	public function getSettings( string $instanceId, ?string $dashboardSlug = null, mixed $default = [] ): array
	{
		return App::make( DashboardWidgetsManager::class )->getUserWidgetInstanceSettings( $instanceId, $dashboardSlug, $default );
	}

	/**
	 * Save current user's settings for a specific instance of this widget.
	 *
	 * @since 1.1.0
	 *
	 * @param string      $instanceId    The unique ID of the widget instance.
	 * @param array       $settings      An associative array of settings for this widget instance. This *must* include
	 *                                   an 'order' key.
	 * @param string|null $dashboardSlug Optional. The slug of the specific dashboard. Default null (main dashboard).
	 * @return void
	 */
	public function saveSettings( string $instanceId, array $settings, ?string $dashboardSlug = null ): void
	{
		App::make( DashboardWidgetsManager::class )->saveUserWidgetInstanceSettings( $instanceId, $settings, $dashboardSlug );
	}

	/**
	 * Get the rendered content of the widget.
	 *
	 * This method will attempt to render either a Livewire component or a Blade view.
	 * Child classes can override this for custom rendering logic.
	 *
	 * @since 1.1.0
	 *
	 * @param string $instanceId The unique ID of this widget instance.
	 * @param array  $data       Optional. Data to pass to the view or Livewire component. Default empty array.
	 * @return string The rendered HTML content.
	 */
	public function render( string $instanceId, array $data = [] ): string
	{
		// Pass the instanceId to the view/component for unique identification
		$data['widgetInstanceId'] = $instanceId;

		if ( $this->component ) {
			// Ensure Livewire is available.
			if ( class_exists( Livewire::class ) ) {
				return Livewire::mount( $this->component, $data )->html();
			}
			return 'Livewire not configured.'; // Or throw an exception.
		} else if ( $this->view ) {
			return view( $this->view, $data )->render();
		}

		return ''; // Or throw an exception for no renderable content.
	}

	/**
	 * Initialize the widget.
	 *
	 * Sets the widget properties by calling the define method.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function init(): void
	{
		$this->define();
	}

	/**
	 * Defines the properties of the widget.
	 *
	 * Child classes must implement this method to set their widget type, name, slug, etc.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	abstract protected function define(): void;
}