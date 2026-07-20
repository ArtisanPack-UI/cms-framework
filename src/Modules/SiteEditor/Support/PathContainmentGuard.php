<?php

/**
 * Path Containment Guard
 *
 * Resolves a candidate filesystem path against a base directory and returns
 * the canonicalized absolute path only when it demonstrably lives inside
 * (a descendant of) that base. Returns null in every other case — the path
 * does not exist, the base does not exist, or realpath resolution escapes
 * the base (via `..`, an absolute segment, or a symlink pointing out of
 * tree).
 *
 * Centralizes the realpath + `str_starts_with` pattern that was previously
 * inlined in `ThemeAssetsController::show()`,
 * `packages/visual-editor/.../GlobalStylesController::readThemeStylesheet()`,
 * and other resolvers — a security-sensitive check that should have one
 * canonical implementation so future hardening (Unicode normalization,
 * null-byte rejection, per-platform separator quirks) lands in one place.
 *
 * @since      2.5.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support;

/**
 * @since 2.5.0
 */
final class PathContainmentGuard
{
    /**
     * Resolve `$candidate` against `$base` and return the canonicalized
     * absolute path when it lives strictly inside `$base`. Returns null
     * otherwise. `$candidate` must be a descendant — a candidate that
     * resolves to exactly `$base` itself is rejected (the guard is for
     * files, not the containing directory).
     *
     * @since 2.5.0
     *
     * @param  string  $base       The directory the candidate must live under.
     * @param  string  $candidate  The path to check.
     *
     * @return string|null Canonicalized absolute path, or null when the
     *                     candidate is missing or escapes `$base`.
     */
    public static function within( string $base, string $candidate ): ?string
    {
        $resolved = realpath( $candidate );
        $baseReal = realpath( $base );

        if ( false === $resolved || false === $baseReal ) {
            return null;
        }

        if ( ! str_starts_with( $resolved, $baseReal . DIRECTORY_SEPARATOR ) ) {
            return null;
        }

        return $resolved;
    }
}
