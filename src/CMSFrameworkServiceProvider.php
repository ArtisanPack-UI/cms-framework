<?php

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

use ArtisanPackUI\CMSFramework\Contracts\ContentManagerInterface;
use ArtisanPackUI\CMSFramework\Contracts\MediaManagerInterface;
use ArtisanPackUI\CMSFramework\Contracts\PluginManagerInterface;
use ArtisanPackUI\CMSFramework\Contracts\SettingsManagerInterface;
use ArtisanPackUI\CMSFramework\Contracts\TaxonomyManagerInterface;
use ArtisanPackUI\CMSFramework\Contracts\ThemeManagerInterface;
use ArtisanPackUI\CMSFramework\Contracts\UserManagerInterface;
use ArtisanPackUI\CMSFramework\Features\AdminPages\AdminPagesManager;
use ArtisanPackUI\CMSFramework\Features\AdminPages\AdminPagesServiceProvider;
use ArtisanPackUI\CMSFramework\Features\Audit\AuditLogServiceProvider;
use ArtisanPackUI\CMSFramework\Features\Auth\TwoFactorAuthServiceProvider;
use ArtisanPackUI\CMSFramework\Features\ContentTypes\ContentTypeManager;
use ArtisanPackUI\CMSFramework\Features\ContentTypes\TaxonomyManager;
use ArtisanPackUI\CMSFramework\Features\DashboardWidgets\DashboardWidgetsServiceProvider;
use ArtisanPackUI\CMSFramework\Features\Media\MediaManager;
use ArtisanPackUI\CMSFramework\Features\Notifications\NotificationServiceProvider;
use ArtisanPackUI\CMSFramework\Features\Plugins\PluginManager;
use ArtisanPackUI\CMSFramework\Features\Settings\SettingsManager;
use ArtisanPackUI\CMSFramework\Features\Settings\SettingsServiceProvider;
use ArtisanPackUI\CMSFramework\Features\Themes\ThemeManager;
use ArtisanPackUI\CMSFramework\Features\Users\UsersManager;
use ArtisanPackUI\CMSFramework\Features\Users\UsersServiceProvider;
use ArtisanPackUI\CMSFramework\Http\Middleware\CmsRateLimitingMiddleware;
use ArtisanPackUI\MediaLibrary\MediaLibraryServiceProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use TorMorten\Eventy\Facades\Eventy;

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
        $this->loadMigrationsFrom($this->getMigrationDirectories());
        $this->loadViewsFromDirectories($this->getViewsDirectories());

        // Register rate limiting middleware
        $this->registerRateLimitingMiddleware();

        // Load the main API routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->publishes([
            __DIR__.'/../config/cms.php' => config_path('cms.php'),
        ], 'cms-config');
        // Publish Sanctum's configuration.
        // This will allow the main application to publish them if needed.
        $this->publishes([
            __DIR__.'/../../vendor/laravel/sanctum/config/sanctum.php' => config_path('sanctum.php'),
        ], 'sanctum-config');

        // Register PWA routes and settings when the CMS Framework boots.
        $this->registerPwaFeatures();
        app(AdminPagesManager::class)->registerRoutes();

        // Load the active theme's service provider and its main class.
        $this->loadActiveTheme();
    }

    /**
     * Register rate limiting middleware with the router.
     *
     * Registers the CMS rate limiting middleware with different aliases for
     * different endpoint types (general, auth, admin, upload).
     *
     * @since 1.0.0
     */
    protected function registerRateLimitingMiddleware(): void
    {
        $router = $this->app['router'];

        // Register the middleware with different aliases for different rate limit types
        $router->aliasMiddleware('cms.rate_limit.general', CmsRateLimitingMiddleware::class.':general');
        $router->aliasMiddleware('cms.rate_limit.auth', CmsRateLimitingMiddleware::class.':auth');
        $router->aliasMiddleware('cms.rate_limit.admin', CmsRateLimitingMiddleware::class.':admin');
        $router->aliasMiddleware('cms.rate_limit.upload', CmsRateLimitingMiddleware::class.':upload');

        // Also register a generic alias that defaults to general
        $router->aliasMiddleware('cms.rate_limit', CmsRateLimitingMiddleware::class.':general');
    }

    /**
     * Returns an array of migration directories to load.
     *
     * This method is used to allow for customization of the migration directories
     * by other modules.
     *
     * @since 1.0.0
     * @see   CMSFrameworkServiceProvider
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     *
     * @return array List of migration directories.
     */
    public function getMigrationDirectories(): array
    {
        $defaultDirectories = [
            __DIR__.'/../database/migrations',
            __DIR__.'/../../vendor/laravel/sanctum/database/migrations',
        ];

        /**
         * Loads the migration directories from the modules.
         *
         * Grabs the migration directories from the modules that have been registered and returns them as an array.
         *
         * @since 1.0.0
         *
         * @param  array  $directories  List of directories to load migrations from.
         */
        return Eventy::filter('ap.cms.migrations.directories', $defaultDirectories);
    }

    /**
     * Loads views from the specified directories.
     *
     * This method is used to allow for customization of the view directories
     * by other modules.
     *
     * @since 1.0.0
     * @see   CMSFrameworkServiceProvider
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     *
     * @param  array  $directories  List of directories to load views from.
     */
    public function loadViewsFromDirectories(array $directories): void
    {
        if ($directories) {
            foreach ($directories as $directory) {
                if (isset($directory['path']) && isset($directory['namespace'])) {
                    $this->loadViewsFrom($directory['path'], $directory['namespace']);
                }
            }
        }
    }

    /**
     * Returns an array of view directories to load.
     *
     * This method is used to allow for customization of the view directories
     * by other modules.
     *
     * @since 1.0.0
     * @see   CMSFrameworkServiceProvider
     * @link  https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
     *
     * @return array List of view directories.
     */
    public function getViewsDirectories(): array
    {
        /**
         * Loads the view directories from the modules.
         *
         * Grabs the view directories from the modules that have been registered and returns them as an array.
         * The returned array includes the path and namespace for each view directory.
         *
         * @since 1.0.0
         *
         * @param  array  $directories  List of directories to load views from.
         * @return array {
         *               List of view directories.
         *
         * @type string $path        Path to the view directory.
         * @type string $namespace   Namespace for the view directory.
         *              }
         */
        return Eventy::filter('ap.cms.views.directories', []);
    }

    /**
     * Registers PWA-related features.
     *
     * Includes PWA routes and registers default settings.
     *
     * @since 1.1.0
     */
    protected function registerPwaFeatures(): void
    {
        // Load PWA routes from a dedicated file.
        $this->loadRoutesFrom(__DIR__.'/Features/PWA/routes.php');

        // Register default PWA settings.
        $this->app->make(SettingsManagerInterface::class)->registerPwaDefaults();

        // Load PWA views
        $this->loadViewsFrom(__DIR__.'/Features/PWA/resources/views', 'pwa');
    }

    /**
     * Loads the currently active theme's service provider and main Themes class.
     *
     * This method attempts to get the active theme from the ThemeManager and
     * then registers its service provider and instantiates its Themes class
     * to ensure theme-specific hooks are registered.
     *
     * @since 1.0.0
     */
    protected function loadActiveTheme(): void
    {
        $themeManager = $this->app->make(ThemeManagerInterface::class);
        $activeThemeName = $themeManager->getActiveTheme();

        if ($activeThemeName !== null) {
            $themeServiceProviderClass = 'App\\Themes\\'.Str::studly($activeThemeName).'\\ThemeServiceProvider';
            if (class_exists($themeServiceProviderClass)) {
                $this->app->register($themeServiceProviderClass);
            }

            // Instantiate the theme's main class to register its hooks.
            $themeManager->loadActiveThemeClass();
        }
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
            __DIR__.'/../config/cms.php', 'cms'
        );
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(UsersServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(AuditLogServiceProvider::class);
        $this->app->register(TwoFactorAuthServiceProvider::class);
        $this->app->register(MediaLibraryServiceProvider::class);
        $this->app->register(NotificationServiceProvider::class);
        $this->app->register(AdminPagesServiceProvider::class);
        $this->app->register(DashboardWidgetsServiceProvider::class);
        $this->app->singleton(ContentTypeManager::class, function ($app) {
            return new ContentTypeManager;
        });
        $this->app->singleton(TaxonomyManager::class, function ($app) {
            return new TaxonomyManager;
        });
        $this->app->singleton(CMSManager::class, function ($app) {
            return new CMSManager;
        });
        $this->app->singleton(PluginManager::class, function ($app) {
            return new PluginManager;
        });
        // Register the ThemeManager as a singleton.
        $this->app->singleton(ThemeManager::class, function ($app) {
            return new ThemeManager;
        });

        // Bind interfaces to their concrete implementations
        $this->app->bind(ContentManagerInterface::class, ContentTypeManager::class);
        $this->app->bind(TaxonomyManagerInterface::class, TaxonomyManager::class);
        $this->app->bind(PluginManagerInterface::class, PluginManager::class);
        $this->app->bind(ThemeManagerInterface::class, ThemeManager::class);
        $this->app->bind(SettingsManagerInterface::class, SettingsManager::class);
        $this->app->bind(UserManagerInterface::class, UsersManager::class);
        $this->app->bind(MediaManagerInterface::class, MediaManager::class);

        // Register configuration validation services
        $this->registerConfigurationValidationServices();

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Error handling and API documentation commands
                \ArtisanPackUI\CMSFramework\Console\Commands\ErrorLogViewCommand::class,
                \ArtisanPackUI\CMSFramework\Console\Commands\ErrorAnalysisCommand::class,
                \ArtisanPackUI\CMSFramework\Console\Commands\ErrorLogCleanupCommand::class,
                \ArtisanPackUI\CMSFramework\Console\Commands\ErrorTestingCommand::class,
                \ArtisanPackUI\CMSFramework\Console\Commands\GenerateApiDocsCommand::class,
                
                // User management commands
                \ArtisanPackUI\CMSFramework\Console\Commands\UserCreateCommand::class,
                \ArtisanPackUI\CMSFramework\Console\Commands\UserRoleAssignCommand::class,
                \ArtisanPackUI\CMSFramework\Console\Commands\UserListCommand::class,
                
                // Content management commands
                \ArtisanPackUI\CMSFramework\Console\Commands\ContentCreateCommand::class,
                \ArtisanPackUI\CMSFramework\Console\Commands\ContentPublishCommand::class,
                \ArtisanPackUI\CMSFramework\Console\Commands\ContentCleanupCommand::class,
                
                // Theme/Plugin scaffolding commands
                \ArtisanPackUI\CMSFramework\Console\Commands\ThemeScaffoldCommand::class,
                \ArtisanPackUI\CMSFramework\Console\Commands\PluginScaffoldCommand::class,
                \ArtisanPackUI\CMSFramework\Console\Commands\ComponentScaffoldCommand::class,
                
                // Database seeding commands
                \ArtisanPackUI\CMSFramework\Console\Commands\CmsSeedCommand::class,
                
                // Configuration validation commands
                \ArtisanPackUI\CMSFramework\Features\Configuration\Commands\ConfigTestCommand::class,
                
                // Cache management commands (existing)
                \ArtisanPackUI\CMSFramework\Console\Commands\CacheClearCommand::class,
                \ArtisanPackUI\CMSFramework\Console\Commands\CacheWarmCommand::class,
                
                // Performance and security testing commands (existing)
                \ArtisanPackUI\CMSFramework\Console\Commands\RunPerformanceTests::class,
                \ArtisanPackUI\CMSFramework\Console\Commands\RunSecurityTests::class,
            ]);
        }
    }
    
    /**
     * Register configuration validation services
     */
    protected function registerConfigurationValidationServices(): void
    {
        // Register ConfigurationValidator as singleton
        $this->app->singleton(
            \ArtisanPackUI\CMSFramework\Features\Configuration\Validation\ConfigurationValidator::class,
            function ($app) {
                return new \ArtisanPackUI\CMSFramework\Features\Configuration\Validation\ConfigurationValidator();
            }
        );
        
        // Register RuntimeConfigurationValidator as singleton
        $this->app->singleton(
            \ArtisanPackUI\CMSFramework\Features\Configuration\Runtime\RuntimeConfigurationValidator::class,
            function ($app) {
                $validator = $app->make(\ArtisanPackUI\CMSFramework\Features\Configuration\Validation\ConfigurationValidator::class);
                return new \ArtisanPackUI\CMSFramework\Features\Configuration\Runtime\RuntimeConfigurationValidator($validator);
            }
        );
        
        // Register ConfigurationMigrator as singleton
        $this->app->singleton(
            \ArtisanPackUI\CMSFramework\Features\Configuration\Migrations\ConfigurationMigrator::class,
            function ($app) {
                return new \ArtisanPackUI\CMSFramework\Features\Configuration\Migrations\ConfigurationMigrator();
            }
        );
        
        // Register ConfigurationDocumentationGenerator as singleton
        $this->app->singleton(
            \ArtisanPackUI\CMSFramework\Features\Configuration\Documentation\ConfigurationDocumentationGenerator::class,
            function ($app) {
                return new \ArtisanPackUI\CMSFramework\Features\Configuration\Documentation\ConfigurationDocumentationGenerator();
            }
        );
        
        // Set up runtime validation bootstrap hook
        $this->app->afterResolving('config', function ($config, $app) {
            if (config('cms.runtime_validation.bootstrap_validation', true)) {
                try {
                    $runtimeValidator = $app->make(\ArtisanPackUI\CMSFramework\Features\Configuration\Runtime\RuntimeConfigurationValidator::class);
                    $runtimeValidator->validateOnBootstrap();
                } catch (\Exception $e) {
                    // Log the error but don't break the application
                    if ($app->bound('log')) {
                        $app->make('log')->error('Configuration validation bootstrap failed: ' . $e->getMessage());
                    }
                }
            }
        });
    }
}
