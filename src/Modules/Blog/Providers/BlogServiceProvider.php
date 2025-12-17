<?php

/**
 * Blog Service Provider
 *
 * Registers the Blog module services and bootstraps routes, views, and migrations.
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Providers;

use ArtisanPackUI\CMSFramework\Modules\Blog\Managers\BlogManager;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag;
use ArtisanPackUI\CMSFramework\Modules\Blog\Policies\PostCategoryPolicy;
use ArtisanPackUI\CMSFramework\Modules\Blog\Policies\PostPolicy;
use ArtisanPackUI\CMSFramework\Modules\Blog\Policies\PostTagPolicy;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentTypeManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Blog module services.
 *
 * @since 2.0.0
 */
class BlogServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @since 2.0.0
     */
    public function register(): void
    {
        // Register BlogManager as singleton
        $this->app->singleton(BlogManager::class, fn () => new BlogManager);

        // Load helpers
        $this->loadHelpers();
    }

    /**
     * Bootstrap any application services.
     *
     * @since 2.0.0
     */
    public function boot(Router $router): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load API routes
        Route::prefix('api/v1')
            ->middleware('api')
            ->group(__DIR__.'/../routes/api.php');

        // Register policies
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(PostCategory::class, PostCategoryPolicy::class);
        Gate::policy(PostTag::class, PostTagPolicy::class);

        // Register blog content type
        $this->registerBlogContentType();
    }

    /**
     * Register the blog content type.
     *
     * @since 2.0.0
     */
    protected function registerBlogContentType(): void
    {
        $contentTypeManager = app(ContentTypeManager::class);

        $contentTypeManager->register([
            'name' => 'Blog Posts',
            'slug' => 'posts',
            'table_name' => 'posts',
            'model_class' => Post::class,
            'description' => 'Blog posts with categories, tags, and archives',
            'hierarchical' => false,
            'has_archive' => true,
            'archive_slug' => 'blog',
            'supports' => ['title', 'content', 'excerpt', 'featured_image', 'author', 'custom_fields'],
            'metadata' => [],
            'public' => true,
            'show_in_admin' => true,
            'icon' => 'fas-newspaper',
            'menu_position' => 20,
        ]);
    }

    /**
     * Load helper functions.
     *
     * @since 2.0.0
     */
    protected function loadHelpers(): void
    {
        $helpersPath = __DIR__.'/../helpers.php';

        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }
    }
}
