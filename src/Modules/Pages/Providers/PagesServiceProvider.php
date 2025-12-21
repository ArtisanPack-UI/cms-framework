<?php

declare( strict_types = 1 );

/**
 * Pages Service Provider
 *
 * Registers the Pages module services and bootstraps routes, views, and migrations.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Providers;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\Pages\Managers\PageManager;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageCategory;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\PageTag;
use ArtisanPackUI\CMSFramework\Modules\Pages\Policies\PageCategoryPolicy;
use ArtisanPackUI\CMSFramework\Modules\Pages\Policies\PagePolicy;
use ArtisanPackUI\CMSFramework\Modules\Pages\Policies\PageTagPolicy;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Pages module services.
 *
 * @since 1.0.0
 */
class PagesServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        // Register PageManager as singleton
        $this->app->singleton( PageManager::class, fn () => new PageManager );

        // Load helpers
        $this->loadHelpers();
    }

    /**
     * Bootstrap any application services.
     *
     * @since 1.0.0
     */
    public function boot( Router $router ): void
    {
        // Load migrations
        $this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );

        // Load API routes
        Route::prefix( 'api/v1' )
            ->middleware( 'api' )
            ->group( __DIR__ . '/../routes/api.php' );

        // Register policies
        Gate::policy( Page::class, PagePolicy::class );
        Gate::policy( PageCategory::class, PageCategoryPolicy::class );
        Gate::policy( PageTag::class, PageTagPolicy::class );

        // Register pages content type
        $this->registerPagesContentType();
    }

    /**
     * Register the pages content type.
     *
     * @since 1.0.0
     */
    protected function registerPagesContentType(): void
    {
        $contentTypeManager = app( ContentTypeManager::class );

        $contentTypeManager->register( [
            'name'          => 'Pages',
            'slug'          => 'pages',
            'table_name'    => 'pages',
            'model_class'   => Page::class,
            'description'   => 'Static pages with hierarchical structure',
            'hierarchical'  => true,
            'has_archive'   => false,
            'archive_slug'  => null,
            'supports'      => ['title', 'content', 'excerpt', 'featured_image', 'author', 'custom_fields', 'page_attributes'],
            'metadata'      => [],
            'public'        => true,
            'show_in_admin' => true,
            'icon'          => 'fas-file-alt',
            'menu_position' => 25,
        ] );
    }

    /**
     * Load helper functions.
     *
     * @since 1.0.0
     */
    protected function loadHelpers(): void
    {
        $helpersPath = __DIR__ . '/../helpers.php';

        if ( file_exists( $helpersPath ) ) {
            require_once $helpersPath;
        }
    }
}
