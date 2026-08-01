<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support;

/**
 * Resolves Configured Paths
 *
 * Shared resolution for update config keys that accept either a path relative
 * to `storage_path()` or an absolute one — `cms.updates.state_path` and
 * `cms.updates.backup_path`.
 *
 * Exists so the three consumers cannot disagree about what "absolute" means.
 * They previously did: only `DIRECTORY_SEPARATOR`-prefixed paths counted, so a
 * configured `C:\updates\state.json` was silently rewritten to
 * `storage_path( 'C:\updates\state.json' )`.
 *
 * @since 2.7.1
 */
trait ResolvesConfiguredPaths
{
    /**
     * Resolve a configured path against `storage_path()` unless it is already
     * absolute.
     *
     * @since 2.7.1
     *
     * @param  string  $configured  Configured path.
     *
     * @return string Absolute path.
     */
    protected function resolveConfiguredPath( string $configured ): string
    {
        return $this->isAbsolutePath( $configured )
            ? $configured
            : storage_path( $configured );
    }

    /**
     * Whether a configured path is already absolute.
     *
     * Handles POSIX roots, Windows drive letters, and UNC paths.
     *
     * @since 2.7.1
     *
     * @param  string  $path  Configured path.
     *
     * @return bool True when the path is absolute.
     */
    protected function isAbsolutePath( string $path ): bool
    {
        if ( str_starts_with( $path, '/' ) || str_starts_with( $path, DIRECTORY_SEPARATOR ) ) {
            return true;
        }

        // `C:\…`, `C:/…`, or a `\\server\share` UNC path.
        return 1 === preg_match( '#^[A-Za-z]:[\\\\/]#', $path ) || str_starts_with( $path, '\\\\' );
    }
}
