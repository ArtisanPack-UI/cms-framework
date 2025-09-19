<?php
/**
 * Manages the registration and routing of admin pages.
 *
 * @since      2.0.0
 * @package    ArtisanPackUI\CMSFramework\Modules\Admin\Managers
 */

namespace ArtisanPackUI\CMSFramework\Modules\Admin\Managers;

use Illuminate\Support\Facades\Route;

class AdminPageManager
{
	protected array $pages = [];

	/**
	 * Stores the details of a page to be registered.
	 *
	 * @since 2.0.0
	 * @param mixed       $view       The view, closure, or controller action.
	 * @param string|null $capability The permission required to view the page.
	 * @param string      $slug       The slug for the page route.
	 */
	public function register( string $slug, mixed $view, ?string $capability ): void
	{
		$this->pages[ $slug ] = [ 'view' => $view, 'capability' => $capability ];
	}

	/**
	 * Creates all the registered admin page routes with security middleware.
	 *
	 * @since 2.0.0
	 */
	public function registerRoutes(): void
	{
		Route::middleware( [ 'web', 'auth' ] )
			 ->prefix( 'admin' )
			 ->name( 'admin.' )
			 ->group( function () {
				 foreach ( $this->pages as $slug => $details ) {
					 $routeName = str_replace( '/', '.', $slug );
					 $route     = null;

					 if ( is_string( $details['view'] ) ) {
						 $route = Route::view( $slug, $details['view'] );
					 } else {
						 $route = Route::get( $slug, $details['view'] );
					 }

					 // Name the route first.
					 $route->name( $routeName );

					 // **THE FIX IS HERE:**
					 // Only apply the security middleware if a capability has been defined for the page.
					 if ( ! empty( $details['capability'] ) ) {
						 $route->middleware( 'admin.can:' . $details['capability'] );
					 }
				 }
			 } );
	}
}