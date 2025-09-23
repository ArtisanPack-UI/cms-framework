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
					 // **THE FIX IS HERE:**
					 // 1. First, remove the dynamic parameter part from the slug.
					 $cleanedSlug = preg_replace( '/\/\{.*?\}/', '', $slug );

					 // 2. Now, create the route name from the cleaned slug.
					 $routeName = str_replace( '/', '.', $cleanedSlug );

					 $route = null;

					 // Check if the original slug contains a dynamic parameter.
					 if ( str_contains( $slug, '{' ) ) {
						 // For dynamic routes, we MUST use Route::get with a closure.
						 $route = Route::get( $slug, function () use ( $details ) {
							 return view( $details['view'], request()->route()->parameters() );
						 } );
					 } else if ( is_string( $details['view'] ) ) {
						 // For simple, static routes, Route::view is the most efficient.
						 $route = Route::view( $slug, $details['view'] );
					 } else {
						 // For other cases, like a closure passed as a view, use Route::get.
						 $route = Route::get( $slug, $details['view'] );
					 }

					 // Name the route with the correctly generated name.
					 $route->name( $routeName );

					 // Use Laravel's built-in 'can:' middleware for reliability.
					 if ( ! empty( $details['capability'] ) ) {
						 $route->middleware( 'can:' . $details['capability'] );
					 }
				 }
			 } );
	}
}