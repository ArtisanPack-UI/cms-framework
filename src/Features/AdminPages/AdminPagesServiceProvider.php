<?php
/**
 * Admin Pages Service Provider
 *
 * Provides the service registration and bootstrapping for the admin pages feature
 * of the CMS framework. This service provider is responsible for defining
 * the registration and bootstrapping process related to the admin pages functionality.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\AdminPages
 * @since      1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Features\AdminPages;

use Illuminate\Support\ServiceProvider;

/**
 * Class for providing admin pages services
 *
 * Provides the necessary methods to register and boot the admin pages services within the application.
 *
 * @since 1.1.0
 * @see   ServiceProvider
 */
class AdminPagesServiceProvider extends ServiceProvider
{
    /**
     * Register admin pages services.
     *
     * Registers the AdminPagesManager as a singleton service in the application container.
     *
     * @since 1.1.0
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton( AdminPagesManager::class, function ( $app ) {
            return new AdminPagesManager();
        } );
    }

    /**
     * Boot admin pages services.
     *
     * This method can be used for any bootstrapping logic related to admin pages,
     * such as loading routes or views for the admin area.
     *
     * @since 1.1.0
     * @return void
     */
    public function boot(): void
    {
        // Load admin routes.
        $this->loadRoutesFrom( __DIR__ . '/../routes/admin.php' );

        // Optionally, load admin-specific views.
        $this->loadViewsFrom( __DIR__ . '/../resources/views/admin', 'ap-cms-admin' );
    }
}
