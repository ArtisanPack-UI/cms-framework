<?php

declare( strict_types=1 );

/**
 * The single navigation-URL allow-list for the admin menu.
 *
 * @since 2.8.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Admin\Support;

use Stringable;

/**
 * Reduces a plugin- or filter-supplied value to something safe to put in an
 * admin nav `<a href>`.
 *
 * Extracted in 2.8.0 because the rules existed twice — once in
 * `AdminMenuManager::sanitizeExternalUrl()` on the render path, once in
 * `PluginServiceProvider::normalizeNavUrl()` on the registry ingress path —
 * with the same regex and the same scheme list, which is exactly how one copy
 * came to be hardened while the other kept storing the un-normalized value.
 *
 * @since 2.8.0
 */
final class NavUrl
{
    /**
     * Schemes an admin nav entry may link to.
     *
     * @since 2.8.0
     *
     * @var array<int,string>
     */
    public const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Allow only navigation-safe URL forms:
     *   - Absolute URLs whose scheme is in {@see self::ALLOWED_SCHEMES}
     *   - Same-origin relative URLs (starting with '/')
     *   - Hash fragments (starting with '#')
     *   - Scheme-less relative references (e.g. 'docs/index.html')
     *
     * Anything else (javascript:, data:, vbscript:, file:, etc.) collapses to
     * '#'. Prevents plugin authors from injecting XSS vectors into the admin
     * sidebar via `registerNavEntry(['external' => true, 'url' => ...])` or an
     * `ap.cmsFramework.admin.menu` subscriber.
     *
     * @since 2.8.0
     *
     * @param  string  $url  The raw URL.
     * @param  string  $context  Named in the warning logged for a refused scheme.
     *
     * @return string A URL safe to render into an `href`.
     */
    public static function sanitize( string $url, string $context = 'AdminMenuManager' ): string
    {
        // Normalize the way the URL parser does before looking for a scheme:
        // it removes every ASCII tab and newline from anywhere in the URL and
        // strips leading and trailing C0 controls and spaces. Without this, a
        // literal tab in "java\tscript:alert(1)" fails the scheme regex below,
        // the value falls through as a "relative reference", and it survives
        // Blade's escaping — htmlspecialchars() leaves control characters
        // alone — to reach the browser as `javascript:`. Checking the
        // normalized form means the allow-list sees what the browser will see,
        // and returning that same form keeps the two from diverging after the
        // check.
        $trimmed = ( string ) preg_replace( '/[\t\n\r]/', '', $url );
        $trimmed = trim( $trimmed, "\x00..\x20\x7F" );

        if ( '' === $trimmed ) {
            return '#';
        }

        if ( str_starts_with( $trimmed, '/' ) || str_starts_with( $trimmed, '#' ) ) {
            return $trimmed;
        }

        // Match absolute URLs with an explicit scheme.
        if ( 1 === preg_match( '#^([a-zA-Z][a-zA-Z0-9+.\-]*):#', $trimmed, $matches ) ) {
            $scheme = strtolower( $matches[1] );
            if ( in_array( $scheme, self::ALLOWED_SCHEMES, true ) ) {
                return $trimmed;
            }

            logger()->warning( "{$context}: refused unsafe URL scheme '{$scheme}' in nav entry." );

            return '#';
        }

        // Scheme-less values that aren't obviously a path — treat as safe
        // relative reference (e.g., 'docs/index.html').
        return $trimmed;
    }

    /**
     * Reduce a menu row's `url` to a plain string before sanitizing it.
     *
     * Deliberately not an `is_string()` guard: Blade's `e()` returns an object
     * implementing `Htmlable` — `HtmlString` among them — *unescaped*, so a
     * non-string `url` would otherwise skip both the allow-list and the
     * escaping and land in the `<a href>` verbatim.
     *
     * @since 2.8.0
     *
     * @param  mixed  $url  The raw `url` value from a menu row.
     * @param  string  $context  Named in the warning logged for a refused scheme.
     *
     * @return string A URL safe to render into an `href`.
     */
    public static function sanitizeValue( mixed $url, string $context = 'AdminMenuManager' ): string
    {
        if ( is_string( $url ) ) {
            return self::sanitize( $url, $context );
        }

        if ( $url instanceof Stringable ) {
            return self::sanitize( ( string ) $url, $context );
        }

        return '#';
    }
}
