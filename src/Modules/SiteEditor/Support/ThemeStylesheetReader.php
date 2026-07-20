<?php

/**
 * Theme Stylesheet Reader
 *
 * Reads the active theme's hand-authored top-level stylesheets from disk
 * and wraps them with a labeled banner so consumers (site-editor canvas
 * `/api/v1/global-styles/css` endpoint, downstream packages) can
 * concatenate them onto emitter output with consistent framing.
 *
 * Two conventional filenames are supported by default:
 *
 *   - `style.css`  — front-end + canvas parity. Loaded by both the
 *     public front-end (via a `<link rel>`) and the site-editor canvas.
 *   - `editor.css` — canvas only, the analog of WordPress's
 *     `add_editor_style()`. Themes use it for rules that must apply
 *     inside the site-editor iframe but must NOT leak to the public
 *     front-end (bare element selectors that would theme
 *     inspector-panel mini-previews, canvas-only chrome overrides,
 *     etc.).
 *
 * The public {@see read()} / {@see readWrapped()} methods accept any
 * filename so callers can extend the convention without subclassing.
 *
 * Path resolution delegates to {@see ThemeManager::getThemesPath()} so
 * the reader stays anchored to the same themes root the rest of the
 * theme subsystem uses. Slug validation delegates to
 * {@see ThemeManager::validateSlug()} for the same reason.
 * Path-traversal containment delegates to {@see PathContainmentGuard}.
 *
 * The active-theme lookup is memoized per instance for the lifetime of
 * the request — {@see ThemeManager::getActiveTheme()} re-parses
 * `theme.json` and re-runs schema validation on every call, and the
 * two conventional reads would otherwise pay that cost twice.
 *
 * The class is intentionally not `final` so downstream packages (see
 * `packages/visual-editor`) can inject and extend it. It is registered
 * as a container binding in `SiteEditorServiceProvider` so callers can
 * `app( ThemeStylesheetReader::class )` without worrying about
 * constructor wiring.
 *
 * @since      2.5.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support;

use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Support\Facades\File;

/**
 * @since 2.5.0
 */
class ThemeStylesheetReader
{
    /**
     * Cached active-theme manifest for the lifetime of this instance.
     * `false` means "not yet resolved"; `null` means "resolved to no
     * active theme"; an array is the manifest.
     *
     * @var array<string, mixed>|false|null
     */
    private array|null|false $activeTheme = false;

    /**
     * @since 2.5.0
     */
    public function __construct(
        private ThemeManager $themeManager,
    ) {
    }

    /**
     * Read the active theme's `style.css`. Convenience wrapper around
     * {@see read()} for the front-end + canvas parity convention.
     *
     * @since 2.5.0
     */
    public function frontendStylesheet(): string
    {
        return $this->read( 'style.css' );
    }

    /**
     * Read the active theme's `editor.css`. Convenience wrapper around
     * {@see read()} for the canvas-only convention.
     *
     * @since 2.5.0
     */
    public function editorStylesheet(): string
    {
        return $this->read( 'editor.css' );
    }

    /**
     * Read an arbitrary top-level stylesheet from the active theme's
     * root directory. Returns an empty string when no theme is active,
     * the slug is invalid, the file does not exist, or the resolved
     * path escapes the configured themes directory. `$filename` must
     * be a bare filename (no `/`, `..`, or backslash); the guard
     * rejects anything else.
     *
     * @since 2.5.0
     *
     * @param  string  $filename  Bare filename inside the theme root, e.g. `style.css`.
     */
    public function read( string $filename ): string
    {
        if ( '' === $filename || false !== strpbrk( $filename, "/\\\0" ) || str_contains( $filename, '..' ) ) {
            return '';
        }

        $theme = $this->resolveActiveTheme();

        if ( null === $theme ) {
            return '';
        }

        $slug = isset( $theme['slug'] ) && is_string( $theme['slug'] ) ? $theme['slug'] : '';

        if ( ! $this->themeManager->validateSlug( $slug ) ) {
            return '';
        }

        $themesBase = $this->themeManager->getThemesPath();
        $stylesheet = $themesBase . '/' . $slug . '/' . $filename;

        $resolved = PathContainmentGuard::within( $themesBase, $stylesheet );

        if ( null === $resolved || ! File::exists( $resolved ) ) {
            return '';
        }

        return File::get( $resolved );
    }

    /**
     * Read `$filename` and wrap it in a CSS `=== filename ===` banner
     * comment so a concatenated response body is easy to inspect in
     * devtools. Returns an empty string when the file is absent, so
     * callers can safely `array_filter` the result before joining.
     *
     * @since 2.5.0
     *
     * @param  string  $filename  Bare filename inside the theme root, e.g. `style.css`.
     */
    public function readWrapped( string $filename ): string
    {
        $contents = $this->read( $filename );

        if ( '' === $contents ) {
            return '';
        }

        return '/* === ' . $filename . ' === */' . "\n" . $contents;
    }

    /**
     * Freshest-known modification time across the two conventional
     * stylesheets, or `null` when neither exists. Callers use this to
     * derive an ETag / Last-Modified header without paying a second
     * `File::get()` on the concatenated body.
     *
     * @since 2.5.0
     *
     * @param  array<int, string>  $filenames  Optional filenames to consider.
     */
    public function lastModified( array $filenames = ['style.css', 'editor.css'] ): ?int
    {
        $theme = $this->resolveActiveTheme();

        if ( null === $theme ) {
            return null;
        }

        $slug = isset( $theme['slug'] ) && is_string( $theme['slug'] ) ? $theme['slug'] : '';

        if ( ! $this->themeManager->validateSlug( $slug ) ) {
            return null;
        }

        $themesBase = $this->themeManager->getThemesPath();
        $latest     = null;

        foreach ( $filenames as $filename ) {
            if ( ! is_string( $filename ) || '' === $filename ) {
                continue;
            }

            $resolved = PathContainmentGuard::within( $themesBase, $themesBase . '/' . $slug . '/' . $filename );

            if ( null === $resolved || ! File::exists( $resolved ) ) {
                continue;
            }

            $mtime  = File::lastModified( $resolved );
            $latest = null === $latest ? $mtime : max( $latest, $mtime );
        }

        return $latest;
    }

    /**
     * Memoized `getActiveTheme()` accessor. The manager's own call is
     * not memoized and re-runs a settings lookup + `theme.json` parse
     * + schema validation on every invocation, so the two conventional
     * reads would otherwise pay that cost twice per request.
     *
     * @since 2.5.0
     *
     * @return array<string, mixed>|null
     */
    protected function resolveActiveTheme(): ?array
    {
        if ( false === $this->activeTheme ) {
            $this->activeTheme = $this->themeManager->getActiveTheme();
        }

        return $this->activeTheme;
    }
}
