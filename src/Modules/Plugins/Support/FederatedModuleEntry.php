<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Support;

/**
 * Classifies a federated module `entry` value.
 *
 * An entry is either an absolute `http(s)` URL the host can hand straight to
 * its federation runtime, or a path relative to the plugin directory that the
 * framework serves through its plugin asset route
 * ( `/plugins/{slug}/assets/{path}` ) and resolves to an absolute URL.
 *
 * Shared by manifest validation in
 * {@see \ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager}
 * and registration in {@see PluginServiceProvider}, so a value the validator
 * accepts is always one the provider can resolve.
 *
 * @since 2.14.0
 */
class FederatedModuleEntry
{
    /**
     * Whether the entry is an absolute `http` or `https` URL with a host.
     *
     * The whole value must be a well-formed URL ( no whitespace, no control
     * characters ), and credentials ( `https://user:pass@host` ) are refused,
     * since the entry reaches the browser.
     *
     * @since 2.14.0
     *
     * @param  string  $entry  The entry value.
     * @param  bool  $httpsOnly  Accept only `https` when true.
     */
    public static function isAbsoluteUrl( string $entry, bool $httpsOnly = false ): bool
    {
        $parts = self::parseUrl( $entry );

        if ( null === $parts ) {
            return false;
        }

        return in_array( $parts['scheme'], $httpsOnly ? ['https'] : ['http', 'https'], true );
    }

    /**
     * Whether the entry is an absolute `http` URL to a loopback host, i.e. a
     * local dev server such as `vite --port 5174`.
     *
     * Loopback hosts are `localhost`, any `*.localhost` name, `127.0.0.0/8`
     * and `[::1]`. Traffic to them never leaves the machine, so plaintext
     * `http` is safe to allow there.
     *
     * @since 2.14.0
     *
     * @param  string  $entry  The entry value.
     */
    public static function isLoopbackHttpUrl( string $entry ): bool
    {
        $parts = self::parseUrl( $entry );

        if ( null === $parts || 'http' !== $parts['scheme'] ) {
            return false;
        }

        $host = $parts['host'];

        if ( 'localhost' === $host || str_ends_with( $host, '.localhost' ) || '[::1]' === $host ) {
            return true;
        }

        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- filter_var( FILTER_VALIDATE_IP ) is the validation; only its boolean result is used.
        return false !== filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && str_starts_with( $host, '127.' );
    }

    /**
     * Whether the entry is a relative path the plugin asset route can serve.
     *
     * The path must sit in a subdirectory of the plugin ( `dist/remoteEntry.js`,
     * not `remoteEntry.js` ): the entry's directory becomes the public asset
     * root, so an entry at the plugin root would publish the plugin's source
     * and manifest. Absolute paths, URL schemes, protocol-relative URLs,
     * backslashes, null bytes and `.` / `..` segments are refused.
     *
     * The file name becomes the asset URL's `{path}`, so it must match the
     * route's character set ( letters, digits, `.`, `_`, `@`, `-` ) and must
     * not start with a dot, which the asset controller refuses to serve.
     * Directory segments never appear in the URL and are not restricted
     * further.
     *
     * @since 2.14.0
     *
     * @param  string  $entry  The entry value.
     */
    public static function isServableRelativePath( string $entry ): bool
    {
        if ( '' === $entry
            || str_contains( $entry, "\0" )
            || str_contains( $entry, '\\' )
            || str_starts_with( $entry, '/' )
            || 1 === preg_match( '#^[A-Za-z][A-Za-z0-9+.-]*:#', $entry ) ) {
            return false;
        }

        $segments = explode( '/', $entry );

        foreach ( $segments as $segment ) {
            if ( '' === $segment || '.' === $segment || '..' === $segment ) {
                return false;
            }
        }

        if ( 1 !== preg_match( '#^[A-Za-z0-9_@-][A-Za-z0-9._@-]*$#D', end( $segments ) ) ) {
            return false;
        }

        return count( $segments ) >= 2;
    }

    /**
     * Parse an absolute `http(s)` URL into its lower-cased scheme and host.
     *
     * Returns null for anything that is not a well-formed absolute URL with
     * a host, or that carries credentials.
     *
     * @since 2.14.0
     *
     * @param  string  $entry  The entry value.
     *
     * @return array{scheme:string,host:string}|null
     */
    protected static function parseUrl( string $entry ): ?array
    {
        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- filter_var( FILTER_VALIDATE_URL ) is the validation; only its boolean result is used.
        if ( false === filter_var( $entry, FILTER_VALIDATE_URL ) ) {
            return null;
        }

        $parts = parse_url( $entry );

        if ( ! is_array( $parts )
            || ! isset( $parts['scheme'], $parts['host'] )
            || '' === $parts['host']
            || isset( $parts['user'] )
            || isset( $parts['pass'] ) ) {
            return null;
        }

        return [
            'scheme' => strtolower( $parts['scheme'] ),
            'host'   => strtolower( $parts['host'] ),
        ];
    }
}
