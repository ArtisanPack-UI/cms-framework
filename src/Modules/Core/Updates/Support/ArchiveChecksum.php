<?php

/**
 * Archive Checksum
 *
 * The single home for SHA-256 digest normalization and comparison shared by
 * the application updater, the theme/plugin update managers, and the update
 * sources — so an uppercase or padded digest is accepted (or rejected) the
 * same way everywhere.
 *
 * @since      2.8.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support;

/**
 * Normalizes and verifies SHA-256 archive digests.
 *
 * @since 2.8.0
 */
final class ArchiveChecksum
{
    /**
     * Normalize a digest to canonical lowercase 64-hex, or null when unusable.
     *
     * A source may publish a digest padded with whitespace or in uppercase;
     * both are valid SHA-256 values and must verify. Anything that is not a
     * 64-character hex string — a truncated value, a non-string, an algorithm
     * prefix — is treated as "no usable digest" so callers fail closed rather
     * than comparing against garbage.
     *
     * @since 2.8.0
     *
     * @param  mixed  $value  The advertised digest.
     *
     * @return string|null Canonical lowercase 64-hex digest, or null.
     */
    public static function normalize( mixed $value ): ?string
    {
        if ( ! is_string( $value ) ) {
            return null;
        }

        $normalized = strtolower( trim( $value ) );

        return 1 === preg_match( '/^[a-f0-9]{64}$/', $normalized ) ? $normalized : null;
    }

    /**
     * Constant-time compare a file's SHA-256 against a normalized digest.
     *
     * @since 2.8.0
     *
     * @param  string  $path  Path to the file to hash.
     * @param  string  $normalizedExpected  Canonical lowercase 64-hex digest.
     *
     * @return bool True when the file's digest matches.
     */
    public static function fileMatches( string $path, string $normalizedExpected ): bool
    {
        return hash_equals( $normalizedExpected, (string) hash_file( 'sha256', $path ) );
    }
}
