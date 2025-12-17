<?php

/**
 * Manages the registration and routing of admin pages.
 *
 * @since      2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Admin\Managers;

use Illuminate\Support\Facades\Route;

/**
 * Registers admin pages and creates their routes with appropriate middleware.
 *
 * Provides an API to register page slugs with actions and optional capabilities,
 * then materializes them into HTTP routes under the /admin prefix.
 *
 * @since 2.0.0
 */
class AdminPageManager
{
    /**
     * Registered pages keyed by slug.
     *
     * @since 2.0.0
     *
     * @var array<string,array{action:mixed,capability:?string}>
     */
    protected array $pages = [];

    /**
     * Stores the details of a page to be registered.
     *
     * @since 2.0.0
     *
     * @param  string  $slug  The slug for the page route.
     * @param  mixed  $action  The view, closure, or controller action.
     * @param  string|null  $capability  The permission required to view the page.
     */
    public function register(string $slug, mixed $action, ?string $capability): void
    {
        $this->pages[$slug] = ['action' => $action, 'capability' => $capability];
    }

    /**
     * Creates all the registered admin page routes with security middleware.
     *
     * @since 2.0.0
     */
    public function registerRoutes(): void
    {
        Route::middleware(['web', 'auth'])
            ->prefix('admin')
            ->name('admin.')
            ->group(function () {
                foreach ($this->pages as $slug => $details) {
                    // Clean the slug to create a predictable route name.
                    $cleanedSlug = preg_replace('/\/\{.*?\}/', '', $slug);
                    $routeName = str_replace('/', '.', $cleanedSlug);

                    // Create the route directly. Laravel handles the rest.
                    $route = Route::get($slug, $details['action'])->name($routeName);

                    // Apply capability middleware if it exists.
                    if (! empty($details['capability'])) {
                        $route->middleware('can:'.$details['capability']);
                    }
                }
            });
    }
}
