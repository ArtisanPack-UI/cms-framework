<?php

/**
 * Site Editor Service Provider
 *
 * Registers the SiteEditor module's resolvers, REST routes, and migrations.
 *
 * Permissions for site-editor entities (`visual_editor.templates.edit`,
 * `visual_editor.template-parts.edit`, etc.) are seeded by the parent
 * {@see \ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider} via the
 * G5 (#98) bridge. SiteEditor relies on those slugs being present without
 * registering its own.
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Providers;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplatePartResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplateResolver;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Support\ServiceProvider;

/**
 * @since 1.2.0
 */
class SiteEditorServiceProvider extends ServiceProvider
{
    /**
     * @since 1.2.0
     */
    public function register(): void
    {
        $this->app->singleton( TemplateResolver::class, function ( $app ) {
            return new TemplateResolver( $app->make( ThemeManager::class ) );
        } );

        $this->app->singleton( TemplatePartResolver::class, function ( $app ) {
            return new TemplatePartResolver( $app->make( ThemeManager::class ) );
        } );
    }

    /**
     * @since 1.2.0
     */
    public function boot(): void
    {
        $this->loadRoutesFrom( __DIR__ . '/../routes/api.php' );
    }
}
