<?php
/**
 * Admin Pages Manager
 *
 * Manages the registration, routing, and display of administrative sections,
 * pages, and subpages within the CMS.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\AdminPages
 * @since      1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Features\AdminPages;

use Illuminate\Support\Facades\Route;
use TorMorten\Eventy\Facades\Eventy;

/**
 * Class for managing admin pages and menus
 *
 * Provides functionality to register and manage CMS administration pages,
 * including main menu items and subpages.
 *
 * @since 1.1.0
 */
class AdminPagesManager
{
	/**
	 * Registered admin menu items.
	 *
	 * @since 1.1.0
	 * @var array
	 */
	protected array $menuItems = [];

	/**
	 * Register an admin menu page.
	 *
	 * Allows modules to register their top-level admin menu pages.
	 *
	 * @since 1.1.0
	 *
	 * @param string      $title      The title of the menu item.
	 * @param string      $slug       The slug for the menu item (used in URL and as a unique identifier).
	 * @param string      $icon       Optional. The icon to display next to the menu item. Default empty string.
	 * @param string|null $view       Optional. The Blade view to load for this page. Default null.
	 * @param string|null $component  Optional. The Livewire component to load for this page. Default null.
	 * @param string|null $permission Optional. The required permission to access this page. Default null.
	 * @return void
	 */
	public function registerPage(
		string $title,
		string $slug,
		string $icon = '',
		?string $view = null,
		?string $component = null,
		?string $permission = null
	): void
	{
		$this->menuItems[ $slug ] = [
			'title'      => $title,
			'slug'       => $slug,
			'icon'       => $icon,
			'view'       => $view,
			'component'  => $component,
			'permission' => $permission,
			'subpages'   => [],
		];
	}

	/**
	 * Register an admin subpage.
	 *
	 * Allows modules to register subpages under a top-level admin menu item.
	 *
	 * @since 1.1.0
	 *
	 * @param string      $parentSlug The slug of the parent menu item.
	 * @param string      $title      The title of the subpage.
	 * @param string      $slug       The slug for the subpage.
	 * @param string|null $view       Optional. The Blade view to load for this subpage. Default null.
	 * @param string|null $component  Optional. The Livewire component to load for this subpage. Default null.
	 * @param string|null $permission Optional. The required permission to access this subpage. Default null.
	 * @return void
	 */
	public function registerSubPage(
		string $parentSlug,
		string $title,
		string $slug,
		?string $view = null,
		?string $component = null,
		?string $permission = null
	): void
	{
		if ( isset( $this->menuItems[ $parentSlug ] ) ) {
			$this->menuItems[ $parentSlug ]['subpages'][ $slug ] = [
				'title'      => $title,
				'slug'       => $slug,
				'view'       => $view,
				'component'  => $component,
				'permission' => $permission,
			];
		}
	}

	/**
	 * Register admin routes based on registered menu items.
	 *
	 * This method should be called during the boot process of the service provider
	 * to define routes for all registered admin pages and subpages.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function registerRoutes(): void
	{
		$menuItems = $this->getMenuItems();

		// Get the base admin path from configuration
		$adminBasePath = config( 'cms.admin_path', 'admin' ); // Default to 'admin' if not set.

		foreach ( $menuItems as $menuItem ) {
			$middleware = $menuItem['permission'] ? [ 'can:' . $menuItem['permission'] ] : [];

			// Register main page route.
			Route::group( [ 'middleware' => $middleware, 'prefix' => $adminBasePath ], function () use ( $menuItem ) {
				$uri = $menuItem['slug'];
				if ( $menuItem['component'] ) {
					Route::get( $uri, $menuItem['component'] )->name( 'admin.' . $menuItem['slug'] );
				} else if ( $menuItem['view'] ) {
					Route::get( $uri, function () use ( $menuItem ) {
						return view( $menuItem['view'] );
					} )->name( 'admin.' . $menuItem['slug'] );
				}
			} );

			// Register subpage routes.
			foreach ( $menuItem['subpages'] as $subpage ) {
				$subpageMiddleware = $subpage['permission'] ? [ 'can:' . $subpage['permission'] ] : [];
				Route::group( [ 'middleware' => $subpageMiddleware, 'prefix' => $adminBasePath . '/' . $menuItem['slug'] ], function () use ( $subpage, $menuItem ) {
					$uri = $subpage['slug'];
					if ( $subpage['component'] ) {
						Route::get( $uri, $subpage['component'] )->name( 'admin.' . $menuItem['slug'] . '.' . $subpage['slug'] );
					} else if ( $subpage['view'] ) {
						Route::get( $uri, function () use ( $subpage ) {
							return view( $subpage['view'] );
						} )->name( 'admin.' . $menuItem['slug'] . '.' . $subpage['slug'] );
					}
				} );
			}
		}
	}

	/**
	 * Get all registered menu items.
	 *
	 * @since 1.1.0
	 * @return array The array of registered menu items.
	 */
	public function getMenuItems(): array
	{
		/**
		 * Filters the registered admin menu items.
		 *
		 * Allows other modules to add, remove, or modify admin menu items.
		 *
		 * @since 1.1.0
		 *
		 * @param array $menuItems The array of registered menu items.
		 */
		return Eventy::filter( 'ap.cms.admin.menuItems', $this->menuItems );
	}
}
