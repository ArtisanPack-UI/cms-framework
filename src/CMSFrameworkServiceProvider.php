<?php

declare(strict_types=1);

/**
 * Service provider for the CMS Framework.
 *
 * This class handles the registration and bootstrapping of the framework, including loading migrations and views and
 * providing necessary hooks for customization using the Eventy system.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework;

use ArtisanPackUI\CMSFramework\Modules\Admin\Providers\AdminServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\AdminWidgets\Providers\AdminWidgetServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Blog\Providers\BlogServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Providers\ContentTypesServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Core\Providers\CoreServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Providers\NotificationServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Pages\Providers\PagesServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Providers\PluginsServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Settings\Providers\SettingsServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Themes\Providers\ThemesServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Users\Providers\UserServiceProvider;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Registers and bootstraps the CMS Framework within the application.
 *
 * The service provider is responsible for binding the framework to the container,
 * initializing necessary components during the bootstrapping process,
 * and loading framework-specific resources, such as migrations.
 *
 * @since 1.0.0
 */
class CMSFrameworkServiceProvider extends ServiceProvider
{
    /**
     * Boots the CMS framework and loads database migration files.
     *
     * This method is triggered during the Laravel bootstrapping process to initialize
     * the CMS framework and register migration paths for the system.
     *
     * @since 1.0.0
     * @see   CMSFrameworkServiceProvider
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     */
    public function boot(): void
    {
        $this->mergeConfiguration();
        $this->validateConfiguration();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cms-framework.php' => config_path('artisanpack/cms-framework.php'),
            ], 'artisanpack-package-config');
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Registers a singleton instance of the CMSFramework within the application container.
     *
     * This method is called by the Laravel framework during the bootstrapping process to run the CMS framework.
     *
     * @since 1.0.0
     * @see   CMSFrameworkServiceProvider
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cms-framework.php', 'artisanpack-cms-framework-temp',
        );

        $this->app->register(UserServiceProvider::class);
        $this->app->register(AdminServiceProvider::class);
        $this->app->register(AdminWidgetServiceProvider::class);
        $this->app->register(CoreServiceProvider::class);
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(NotificationServiceProvider::class);
        $this->app->register(ContentTypesServiceProvider::class);
        $this->app->register(BlogServiceProvider::class);
        $this->app->register(PagesServiceProvider::class);
        $this->app->register(ThemesServiceProvider::class);
        $this->app->register(PluginsServiceProvider::class);
    }

    /**
     * Merges the package's default configuration with the user's customizations.
     *
     * This method ensures that the user's settings in `config/artisanpack.php`
     * take precedence over the package's default values.
     *
     * @since 1.0.0
     */
    protected function mergeConfiguration(): void
    {
        // Get the package's default configuration.
        $packageDefaults = config('artisanpack-cms-framework-temp', []);

        // Get the user's custom configuration from config/artisanpack.php.
        $userConfig = config('artisanpack.cms-framework', []);

        // Merge them, with the user's config overwriting the defaults.
        $mergedConfig = array_replace_recursive($packageDefaults, $userConfig);

        // Set the final, correctly merged configuration.
        config(['artisanpack.cms-framework' => $mergedConfig]);
    }

    /**
     * Validates the package configuration.
     *
     * This method ensures that required configuration values are properly set.
     * Validation is skipped when running in console mode to allow setup commands
     * like `vendor:publish` to run before configuration is complete.
     *
     * @throws InvalidArgumentException If required configuration is missing (non-console only).
     *
     * @since 1.0.0
     */
    protected function validateConfiguration(): void
    {
        // Skip validation in console mode to allow setup commands (vendor:publish,
        // package:discover, etc.) to run before the config has been published.
        if ($this->app->runningInConsole()) {
            return;
        }

        $userModel = config('artisanpack.cms-framework.user_model');

        if (null === $userModel) {
            throw new InvalidArgumentException(
                'The CMS Framework user_model configuration is not set. '.
                'Please publish the configuration file using: '.
                'php artisan vendor:publish --tag=artisanpack-package-config '.
                'Then set the user_model value in config/artisanpack/cms-framework.php to your User model class. '.
                'Example: \'user_model\' => \\App\\Models\\User::class',
            );
        }
    }
}