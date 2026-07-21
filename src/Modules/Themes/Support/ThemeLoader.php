<?php

/**
 * Theme Loader.
 *
 * Discovers and instantiates the active theme's optional `Theme.php` file,
 * driving the per-request lifecycle documented on
 * {@see \ArtisanPackUI\CMSFramework\Modules\Themes\Contracts\Theme}.
 *
 * @since      2.5.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Support;

use ArtisanPackUI\CMSFramework\Modules\Themes\Contracts\Theme;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * Resolves and boots the active theme's PHP entry point.
 *
 * Singleton — instantiated once per request. Callers should treat
 * `activeTheme()` as the single source of truth for the request's Theme
 * instance so the lifecycle methods don't fire twice.
 *
 * **Octane / long-running workers.** The loader uses `require_once`, so
 * once a worker has loaded a theme's `Theme.php`, PHP will not re-read
 * the file even if it changes on disk, and switching to a different
 * theme in the same worker leaves the previous class definition
 * resident (PHP cannot unload classes). Operators running under Octane,
 * RoadRunner, or Swoole should reload their workers after a theme is
 * installed or activated — e.g. by wiring `php artisan octane:reload`
 * to the `theme.installed` / `theme.activated` hooks.
 *
 * @since 2.5.0
 */
class ThemeLoader
{
    /**
     * Resolved theme instance for this request, or null when no theme is
     * active or the active theme does not ship a Theme.php.
     */
    protected ?Theme $theme = null;

    /**
     * True once we've attempted resolution — used to distinguish "not
     * resolved yet" from "resolved to null".
     */
    protected bool $resolved = false;

    /**
     * Constructs the loader.
     *
     * @since 2.5.0
     */
    public function __construct( protected ThemeManager $themeManager )
    {
    }

    /**
     * Return the resolved Theme instance for this request, or null.
     *
     * First call runs discovery: loads `themes/{slug}/Theme.php` (if
     * present), instantiates the class per the naming convention, and
     * fires `registerImageSizes()`, `boot()`, and `extend()` in order.
     * Subsequent calls return the cached instance.
     *
     * Any Throwable raised during discovery or boot is caught and
     * logged — a broken theme file must not take down the whole
     * application. The theme is treated as not-loaded for the remainder
     * of the request.
     *
     * @since 2.5.0
     */
    public function activeTheme(): ?Theme
    {
        if ( $this->resolved ) {
            return $this->theme;
        }

        $this->resolved = true;
        $this->theme    = null;

        $active = $this->themeManager->getActiveTheme();

        if ( null === $active || empty( $active['slug'] ) || ! is_string( $active['slug'] ) ) {
            return null;
        }

        $slug     = $active['slug'];
        $filePath = base_path( config( 'cms.themes.directory', 'themes' ) . '/' . $slug . '/Theme.php' );

        if ( ! File::exists( $filePath ) ) {
            return null;
        }

        try {
            require_once $filePath;
        } catch ( Throwable $e ) {
            Log::warning( 'Failed to load theme Theme.php file.', [
                'slug'  => $slug,
                'path'  => $filePath,
                'error' => $e->getMessage(),
            ] );

            return null;
        }

        $class = $this->resolveClassName( $slug, $active );

        if ( null === $class || ! class_exists( $class ) ) {
            Log::warning( 'Theme.php loaded but expected class was not found.', [
                'slug'  => $slug,
                'class' => $class,
            ] );

            return null;
        }

        if ( ! is_subclass_of( $class, Theme::class ) ) {
            Log::warning( 'Theme class does not extend the Theme base class.', [
                'slug'     => $slug,
                'class'    => $class,
                'expected' => Theme::class,
            ] );

            return null;
        }

        // Provenance check: reject any resolved class that does not live
        // inside the theme's own Theme.php on disk. Without this, a
        // hostile theme could ship a `themeClass` manifest key that
        // points at any first-party or vendor class extending Theme —
        // both triggering autoload side effects and running that class's
        // boot() with the wrong slug.
        try {
            $reflection = new ReflectionClass( $class );
        } catch ( ReflectionException $e ) {
            Log::warning( 'Failed to reflect theme class.', [
                'slug'  => $slug,
                'class' => $class,
                'error' => $e->getMessage(),
            ] );

            return null;
        }

        $classFile = $reflection->getFileName();
        $realClass = false !== $classFile ? realpath( $classFile ) : false;
        $realTheme = realpath( $filePath );

        if ( false === $realClass || false === $realTheme || $realClass !== $realTheme ) {
            Log::warning( 'Theme class was not declared in the theme\'s own Theme.php; refusing to load.', [
                'slug'          => $slug,
                'class'         => $class,
                'declaredIn'    => $classFile,
                'expectedFile'  => $filePath,
            ] );

            return null;
        }

        try {
            /** @var Theme $instance */
            $instance = new $class( $slug );

            $instance->registerImageSizes();
            $instance->boot();
            $instance->extend();
        } catch ( Throwable $e ) {
            Log::warning( 'Theme boot failed.', [
                'slug'  => $slug,
                'class' => $class,
                'error' => $e->getMessage(),
            ] );

            return null;
        }

        return $this->theme = $instance;
    }

    /**
     * Reset the cached instance so the next `activeTheme()` call
     * re-runs discovery. Intended for tests that swap themes mid-run;
     * production code should never need this.
     *
     * @since 2.5.0
     */
    public function reset(): void
    {
        $this->theme    = null;
        $this->resolved = false;
    }

    /**
     * Determine the Theme class name to instantiate.
     *
     * Order:
     *   1. Manifest `themeClass` (fully-qualified) — escape hatch for
     *      multi-slug repos or custom namespaces.
     *   2. `Themes\{PascalSlug}\Theme` — the framework convention.
     *
     * @since 2.5.0
     *
     * @param  string  $slug  Active theme slug.
     * @param  array<string, mixed>  $manifest  Parsed theme.json.
     *
     * @return string|null Fully-qualified class name, or null if the
     *                     manifest override is invalid.
     */
    protected function resolveClassName( string $slug, array $manifest ): ?string
    {
        if ( isset( $manifest['themeClass'] ) ) {
            $override = $manifest['themeClass'];

            if ( ! is_string( $override ) ) {
                return null;
            }

            $override = ltrim( trim( $override ), '\\' );

            // Constrain to valid PHP class name characters and reject
            // empty / relative names. The reflection-based provenance
            // check in activeTheme() enforces file identity, but keeping
            // the shape strict here means we never try to autoload
            // attacker-chosen strings just to observe the failure.
            if ( '' === $override
                || 1 !== preg_match( '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $override ) ) {
                return null;
            }

            return $override;
        }

        return 'Themes\\' . $this->pascalCase( $slug ) . '\\Theme';
    }

    /**
     * Convert a kebab / snake-case slug to PascalCase.
     *
     * @since 2.5.0
     */
    protected function pascalCase( string $slug ): string
    {
        $parts = preg_split( '/[-_]+/', $slug ) ?: [];

        return implode( '', array_map( 'ucfirst', $parts ) );
    }
}
