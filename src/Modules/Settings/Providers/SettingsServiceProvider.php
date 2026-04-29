<?php

declare( strict_types = 1 );

/**
 * Service provider for the Settings module.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Providers;

use ArtisanPackUI\CMSFramework\Modules\Settings\Enums\SettingType;
use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Settings module services and bootstraps Settings routing/middleware.
 *
 * @since 1.0.0
 */
class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->singleton( SettingsManager::class, fn () => new SettingsManager );
    }

    /**
     * Bootstrap any application services.
     *
     * @since 1.0.0
     */
    public function boot( Router $router ): void
    {
        Route::prefix( 'api/v1' )
            ->middleware( 'api' )
            ->group( __DIR__ . '/../routes/api.php' );

        $this->registerSiteSettings();
    }

    /**
     * Registers the WP-shape `site.*` settings consumed by visual-editor's
     * `core/site-title`, `core/site-tagline`, and `core/site-logo` blocks.
     *
     * Defaults follow plan 12 §4.3: titles fall through to `app.name`,
     * URL falls through to `app.url`, and logo/icon ids start as null
     * until the host configures media in the admin UI. Sanitizers are
     * the standard `artisanpack-ui/security` helpers.
     *
     * @since 1.2.0
     */
    protected function registerSiteSettings(): void
    {
        /** @var SettingsManager $settings */
        $settings = $this->app->make( SettingsManager::class );

        $settings->registerSetting( 'site.title',   config( 'app.name', '' ), 'sanitizeText', SettingType::String );
        $settings->registerSetting( 'site.tagline', '',                       'sanitizeText', SettingType::String );
        $settings->registerSetting( 'site.url',     config( 'app.url', '' ),  'sanitizeUrl',  SettingType::String );
        $settings->registerSetting( 'site.logo_id', null,                     'sanitizeInt',  SettingType::Integer );
        $settings->registerSetting( 'site.icon_id', null,                     'sanitizeInt',  SettingType::Integer );
    }
}
