<?php

/**
 * Service provider for the AdminWidgets module.
 *
 * This provider handles the registration of the AdminWidgetManager service
 * within the application container.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\AdminWidgets\Providers;

use ArtisanPackUI\CMSFramework\Modules\AdminWidgets\Services\AdminWidgetManager;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Admin Widgets services.
 *
 * @since 1.0.0
 */
class AdminWidgetServiceProvider extends ServiceProvider
{
    /**
     * Register the admin widget manager service as a singleton.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->singleton(AdminWidgetManager::class, function ($app) {
            return new AdminWidgetManager;
        });
    }
}
