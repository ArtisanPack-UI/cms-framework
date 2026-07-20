<?php

declare( strict_types=1 );

/**
 * Blog Service Provider
 *
 * Registers the Blog module services and bootstraps routes, views, and migrations.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Providers;

use ArtisanPackUI\CMSFramework\Modules\Blog\Managers\BlogManager;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Comment;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostCategory;
use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag;
use ArtisanPackUI\CMSFramework\Modules\Blog\Policies\CommentPolicy;
use ArtisanPackUI\CMSFramework\Modules\Blog\Policies\PostCategoryPolicy;
use ArtisanPackUI\CMSFramework\Modules\Blog\Policies\PostPolicy;
use ArtisanPackUI\CMSFramework\Modules\Blog\Policies\PostTagPolicy;
use ArtisanPackUI\CMSFramework\Modules\Blog\Services\QueryRuntime;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\Pages\Managers\PageManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Blog module services.
 *
 * @since 1.0.0
 */
class BlogServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        // Register BlogManager as singleton
        $this->app->singleton( BlogManager::class, fn () => new BlogManager );

        // G4c-1 — QueryRuntime resolves `core/query` block attributes to a
        // paginated Eloquent result. Bound here so visual-editor's REST
        // endpoint and any in-process caller share one orchestration
        // layer over BlogManager / PageManager / ContentTypeManager.
        $this->app->singleton(
            QueryRuntime::class,
            fn ( $app ) => new QueryRuntime(
                $app->make( BlogManager::class ),
                $app->make( PageManager::class ),
                $app->make( ContentTypeManager::class ),
            ),
        );

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
        $this->loadMigrationsFrom( __DIR__ . '/../Database/migrations' );

        // Register the `comments` rate limiter BEFORE the routes load
        // so the `throttle:comments` middleware attached to the
        // public `POST /api/v1/comments` route resolves.
        $this->registerCommentsRateLimiter();

        // Load API routes
        Route::prefix( 'api/v1' )
            ->middleware( 'api' )
            ->group( __DIR__ . '/../routes/api.php' );

        // Register policies
        Gate::policy( Post::class, PostPolicy::class );
        Gate::policy( PostCategory::class, PostCategoryPolicy::class );
        Gate::policy( PostTag::class, PostTagPolicy::class );
        Gate::policy( Comment::class, CommentPolicy::class );

        // Register blog content type
        $this->registerBlogContentType();
    }

    /**
     * Register the blog content type.
     *
     * @since 1.0.0
     */
    protected function registerBlogContentType(): void
    {
        $contentTypeManager = app( ContentTypeManager::class );

        $contentTypeManager->register( [
            'name'          => 'Blog Posts',
            'slug'          => 'posts',
            'table_name'    => 'posts',
            'model_class'   => Post::class,
            'description'   => 'Blog posts with categories, tags, and archives',
            'hierarchical'  => false,
            'has_archive'   => true,
            'archive_slug'  => 'blog',
            'supports'      => ['title', 'content', 'excerpt', 'featured_image', 'author', 'custom_fields'],
            'metadata'      => [],
            'public'        => true,
            'show_in_admin' => true,
            'icon'          => 'fas-newspaper',
            'menu_position' => 20,
        ] );
    }

    /**
     * Register the `comments` rate limiter used by the public
     * `POST /api/v1/comments` route. Authenticated callers get a
     * generous bucket keyed by user id; unauthenticated guests get
     * a tighter bucket keyed by IP so a single host can't bulk-spam
     * the table. Host apps can override either bucket via the
     * `comments.rate-limit.authenticated` / `comments.rate-limit.guest`
     * hooks filters, or replace the limiter entirely by re-calling
     * `RateLimiter::for( 'comments', ... )` from their own provider
     * after this package boots.
     *
     * @since 2.1.0
     */
    protected function registerCommentsRateLimiter(): void
    {
        RateLimiter::for( 'comments', function ( Request $request ): Limit {
            $user = $request->user();

            if ( null !== $user ) {
                $authenticatedLimit = ( int ) applyFilters(
                    'ap.cmsFramework.comments.rateLimit.authenticated',
                    60,
                );

                return Limit::perMinute( $authenticatedLimit )
                    ->by( 'comments:user:' . $user->getAuthIdentifier() );
            }

            $guestLimit = ( int ) applyFilters(
                'ap.cmsFramework.comments.rateLimit.guest',
                10,
            );

            return Limit::perMinute( $guestLimit )
                ->by( 'comments:ip:' . ( $request->ip() ?? 'unknown' ) );
        } );
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
