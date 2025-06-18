<?php
/**
 * Notification Service Provider
 *
 * Provides the service registration and bootstrapping for the notifications feature
 * of the CMS framework. This service provider is responsible for defining
 * the registration and bootstrapping process related to the notifications functionality.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Features\Notifications
 * @since      1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Features\Notifications;

use Illuminate\Support\ServiceProvider;

/**
 * Class for providing notification services
 *
 * Provides the necessary methods to register and boot the notification services within the application.
 *
 * @since 1.1.0
 * @see   ServiceProvider
 */
class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register notification services
     *
     * Registers the NotificationManager as a singleton service in the application container.
     *
     * @since 1.1.0
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(NotificationManager::class, function ($app) {
            return new NotificationManager();
        });
    }

    /**
     * Boot notification services
     *
     * Performs any additional setup needed for the notification system.
     *
     * @since 1.1.0
     * @return void
     */
    public function boot(): void
    {
        // No additional boot configuration needed at this time
    }
}
