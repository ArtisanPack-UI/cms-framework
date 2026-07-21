<?php

/**
 * Theme base class.
 *
 * Optional per-request extension point for themes that need to run PHP on
 * every request — enqueue CSS/JS, register image sizes, add REST endpoints,
 * or filter block output. Themes stay fully backward compatible when this
 * file is absent from the theme directory.
 *
 * @since      2.5.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Contracts;

use ArtisanPackUI\CMSFramework\Modules\Themes\Support\EnqueuedAssets;

/**
 * Abstract base class themes may extend to hook into the per-request
 * lifecycle.
 *
 * Discovery: cms-framework looks for `themes/{slug}/Theme.php` at boot
 * when a theme is active. If present, the class following the
 * `Themes\{PascalSlug}\Theme` naming convention (or the class named
 * in the manifest's `themeClass` key) is instantiated and its lifecycle
 * methods are invoked once per request.
 *
 * Order of invocation, once per request:
 *
 *   1. `registerImageSizes()` — declare image sizes before any request
 *      code (e.g. controllers) would need them.
 *   2. `boot()` — general per-request setup. Analog to WordPress's
 *      `after_setup_theme` + `wp_enqueue_scripts`.
 *   3. `extend()` — final extension point for custom REST endpoints,
 *      block filters, etc.
 *
 * Enqueue methods (`frontendStyles()`, `editorStyles()`,
 * `frontendScripts()`) are called lazily when the corresponding Blade
 * directive renders. They should be pure — returning the same list
 * each call — since the framework does not cache them for you.
 *
 * @since 2.5.0
 */
abstract class Theme
{
    /**
     * Constructs the Theme instance.
     *
     * The active theme's slug is injected so subclasses can build asset
     * URLs, resolve theme-relative paths, or key cache entries without
     * hardcoding the slug (which may differ from the class namespace in
     * multi-slug repos).
     *
     * @since 2.5.0
     *
     * @param  string  $slug  Slug of the theme this instance backs.
     */
    public function __construct( protected string $slug )
    {
    }

    /**
     * Fires on every request after `registerImageSizes()` and before
     * `extend()`.
     *
     * Analog to WordPress's `after_setup_theme` + `wp_enqueue_scripts`.
     * Override to run per-request setup that isn't captured by the
     * dedicated enqueue / image-size / extension hooks.
     *
     * @since 2.5.0
     */
    public function boot(): void
    {
    }

    /**
     * Returns the CSS files to enqueue on the front-end.
     *
     * Each entry is either:
     *  - a string — a path relative to the theme's `assets/` directory
     *    (e.g. `'main.css'`), or
     *  - an array with a `src` key and any subset of `media`, `deps`,
     *    `ver` — the URL is still resolved from `assets/`.
     *
     * String keys become the enqueue handle; numeric keys derive the
     * handle from the file basename.
     *
     * @since 2.5.0
     *
     * @return array<int|string, array<string, mixed>|string>
     */
    public function frontendStyles(): array
    {
        return [];
    }

    /**
     * Returns the CSS files to enqueue inside the editor canvas.
     *
     * Analog to WordPress's `add_editor_style`. Same entry shape as
     * {@see frontendStyles()}.
     *
     * @since 2.5.0
     *
     * @return array<int|string, array<string, mixed>|string>
     */
    public function editorStyles(): array
    {
        return [];
    }

    /**
     * Returns the JS files to enqueue on the front-end.
     *
     * Each entry is either:
     *  - a string — a path relative to the theme's `assets/` directory
     *    (e.g. `'main.js'`), or
     *  - an array with a `src` key and any subset of `defer`, `async`,
     *    `deps`, `ver`, `type`, `module` — the URL is still resolved
     *    from `assets/`.
     *
     * @since 2.5.0
     *
     * @return array<int|string, array<string, mixed>|string>
     */
    public function frontendScripts(): array
    {
        return [];
    }

    /**
     * Registers custom image sizes at boot.
     *
     * Runs before `boot()` so any request code that queries image
     * variants sees the theme's registrations. The default implementation
     * is a no-op; override to call the host app's image-size registry.
     *
     * @since 2.5.0
     */
    public function registerImageSizes(): void
    {
    }

    /**
     * Final extension point for anything the dedicated hooks don't cover.
     *
     * Runs after `boot()` — a good place to bind custom REST endpoints
     * (via the router), register block filters (via `addFilter()`), or
     * wire event listeners that depend on the theme being fully set up.
     *
     * @since 2.5.0
     */
    public function extend(): void
    {
    }

    /**
     * Slug of the theme this instance backs.
     *
     * @since 2.5.0
     */
    public function slug(): string
    {
        return $this->slug;
    }

    /**
     * Build a URL to an asset shipped in the theme's `assets/` directory.
     *
     * The URL resolves against the `themes.assets` named route, which the
     * `ThemesServiceProvider` registers as `/themes/{slug}/assets/{path}`.
     *
     * @since 2.5.0
     *
     * @param  string  $path  Path relative to the theme's `assets/` directory.
     *
     * @return string Absolute asset URL for the current theme.
     */
    public function assetUrl( string $path ): string
    {
        return EnqueuedAssets::assetUrl( $this->slug, $path );
    }
}
