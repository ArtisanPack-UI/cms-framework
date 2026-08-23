<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Support;

use Composer\Semver\Semver;
use Throwable;

/**
 * Pure resolution logic for a plugin's Composer-package requirements.
 *
 * Operates entirely on an in-memory snapshot of installed package versions so
 * it can be unit-tested without touching Composer, Packagist, or the
 * filesystem. `PluginManager` snapshots the installed versions from Composer's
 * runtime `InstalledVersions` and delegates the constraint matching here.
 *
 * The Composer-package sibling of {@see DependencyResolver} (plugin→plugin,
 * #45); both reuse `composer/semver` for constraint matching.
 *
 * @since 2.10.0
 */
class ComposerPackageResolver
{
    /**
     * Resolve a plugin's declared Composer requirements against installed
     * versions.
     *
     * @since 2.10.0
     *
     * @param  array<string,string>  $requirements  Map of package name to version constraint.
     * @param  array<string,string>  $installedVersions  Map of installed package name to version.
     *
     * @return ComposerPackageResult The buckets of unmet requirements.
     */
    public function resolve( array $requirements, array $installedVersions ): ComposerPackageResult
    {
        $missing         = [];
        $versionMismatch = [];

        foreach ( $requirements as $package => $constraint ) {
            if ( ! is_string( $package ) || ! is_string( $constraint ) ) {
                continue;
            }

            $installed = $installedVersions[ $package ] ?? null;

            if ( null === $installed ) {
                $missing[] = [
                    'package'  => $package,
                    'required' => $constraint,
                ];

                continue;
            }

            if ( ! $this->satisfies( $installed, $constraint ) ) {
                $versionMismatch[] = [
                    'package'   => $package,
                    'required'  => $constraint,
                    'installed' => $installed,
                ];
            }
        }

        return new ComposerPackageResult( $missing, $versionMismatch );
    }

    /**
     * Whether an installed version satisfies a semver constraint.
     *
     * Wraps composer/semver, treating any unparseable version or constraint as
     * an unmet requirement rather than letting the exception escape — the same
     * defensive policy {@see DependencyResolver::satisfies()} applies.
     *
     * @since 2.10.0
     *
     * @param  mixed  $version  Installed version (a non-string counts as unmet).
     * @param  mixed  $constraint  Declared constraint (e.g. "^1.2", "*").
     *
     * @return bool True when the version satisfies the constraint.
     */
    protected function satisfies( mixed $version, mixed $constraint ): bool
    {
        if ( ! is_string( $version ) || ! is_string( $constraint ) ) {
            return false;
        }

        $normalized = ltrim( $version, 'vV' );

        try {
            return Semver::satisfies( $normalized, $constraint );
        } catch ( Throwable $e ) {
            return false;
        }
    }
}
