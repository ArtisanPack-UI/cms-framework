<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Support;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Contracts\ComposerPackageInstallerInterface;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\ComposerDependencyNotSatisfiedException;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Default {@see ComposerPackageInstallerInterface} that resolves installed
 * versions from Composer's runtime metadata and shells out to the `composer`
 * binary to bring a plugin's declared packages into the host's vendor tree.
 *
 * Runs `composer require` in the host root so the new package is added to the
 * host's `composer.json` / `composer.lock` and its autoloader is regenerated —
 * the requirement then survives a fresh deploy, unlike a hand-run interim
 * install. Composer itself arbitrates any conflict with the host's existing
 * lock; a non-zero exit unwinds the activation through a fail-closed exception.
 *
 * @since 2.10.0
 */
class ProcessComposerPackageInstaller implements ComposerPackageInstallerInterface
{
    /**
     * The composer command used when neither config nor env overrides it.
     *
     * @since 2.10.0
     */
    protected const DEFAULT_COMPOSER_BINARY = 'composer';

    /**
     * {@inheritDoc}
     *
     * @since 2.10.0
     *
     * @return array<string,string>
     */
    public function installedVersions(): array
    {
        if ( ! class_exists( InstalledVersions::class ) ) {
            return [];
        }

        $versions = [];

        try {
            foreach ( InstalledVersions::getInstalledPackages() as $package ) {
                $version = InstalledVersions::getVersion( $package );
                if ( is_string( $version ) && '' !== $version ) {
                    $versions[ $package ] = $version;
                }
            }
        } catch ( Throwable $e ) {
            return $versions;
        }

        return $versions;
    }

    /**
     * {@inheritDoc}
     *
     * @since 2.10.0
     *
     * @param  array<string,string>  $constraints
     *
     * @throws ComposerDependencyNotSatisfiedException
     */
    public function install( string $slug, array $constraints ): void
    {
        if ( empty( $constraints ) ) {
            return;
        }

        $specs = [];
        foreach ( $constraints as $package => $constraint ) {
            $specs[] = escapeshellarg( "{$package}:{$constraint}" );
        }

        $binary  = $this->resolveComposerBinary();
        $timeout = ( int ) config( 'cms.plugins.composerTimeout', 600 );
        // `--no-plugins` keeps a malicious dependency of type `composer-plugin`
        // from executing its own code during the require — the marginal
        // supply-chain surface this feature introduces. Scripts are deliberately
        // left enabled: the host's `post-autoload-dump` runs `package:discover`,
        // which is how an installed Laravel package's own provider is
        // auto-registered.
        $command = $binary . ' require ' . implode( ' ', $specs ) . ' --no-interaction --no-progress --no-plugins --with-all-dependencies';

        try {
            $result = Process::timeout( $timeout )
                ->path( base_path() )
                ->run( $command );
        } catch ( Throwable $e ) {
            throw ComposerDependencyNotSatisfiedException::installationFailed( $slug, $e->getMessage() );
        }

        if ( ! $result->successful() ) {
            $reason = '' !== trim( $result->errorOutput() )
                ? trim( $result->errorOutput() )
                : trim( $result->output() );

            throw ComposerDependencyNotSatisfiedException::installationFailed(
                $slug,
                '' !== $reason ? $reason : 'composer require exited with a non-zero status.',
            );
        }

        // The subprocess regenerated `vendor/composer/installed.php`, but this
        // long-running process still holds the pre-install snapshot in
        // InstalledVersions' static cache. Reload it so a subsequent
        // installedVersions()/installPath() call in the same request reflects
        // the package that was just installed.
        $this->reloadInstalledVersions();
    }

    /**
     * {@inheritDoc}
     *
     * @since 2.10.0
     */
    public function installPath( string $package ): ?string
    {
        if ( ! class_exists( InstalledVersions::class ) ) {
            return null;
        }

        try {
            if ( ! InstalledVersions::isInstalled( $package ) ) {
                return null;
            }

            $path = InstalledVersions::getInstallPath( $package );
        } catch ( Throwable $e ) {
            return null;
        }

        return is_string( $path ) && '' !== $path ? $path : null;
    }

    /**
     * Refresh Composer's in-process installed-package metadata from the
     * regenerated `vendor/composer/installed.php`.
     *
     * A plain `require` (not `require_once`) re-evaluates the file, so the
     * freshly written array is returned even though the file was already loaded
     * at bootstrap.
     *
     * @since 2.10.0
     */
    protected function reloadInstalledVersions(): void
    {
        if ( ! class_exists( InstalledVersions::class ) ) {
            return;
        }

        $path = base_path( 'vendor/composer/installed.php' );

        try {
            if ( ! is_file( $path ) ) {
                return;
            }

            // The subprocess just rewrote this file, but OPcache may still hold
            // the pre-install bytecode — with `opcache.validate_timestamps=0`
            // (common in production) a plain `require` returns the stale array,
            // which would make the post-install re-resolve wrongly report the
            // package still missing and fail-close a successful activation.
            if ( function_exists( 'opcache_invalidate' ) ) {
                opcache_invalidate( $path, true );
            }

            $data = require $path;

            if ( is_array( $data ) ) {
                InstalledVersions::reload( $data );
            }
        } catch ( Throwable $e ) {
            // Best-effort: the on-disk install still succeeded, and the next
            // request boots with a fresh autoloader that sees the package.
        }
    }

    /**
     * Resolve the composer command base, honouring a config override and the
     * `COMPOSER_BINARY` env escape hatch used elsewhere in the framework for
     * sandboxed PHP-FPM pools that hide the binary from PHP's own filesystem
     * view.
     *
     * Both overrides are operator-supplied and returned verbatim (not
     * shell-escaped) so a multi-token command such as `php /path/composer.phar`
     * works from either source. Only operators set these — a plugin manifest
     * cannot reach them.
     *
     * @since 2.10.0
     */
    protected function resolveComposerBinary(): string
    {
        $configured = config( 'cms.plugins.composerBinary' );
        if ( is_string( $configured ) && '' !== trim( $configured ) ) {
            return $configured;
        }

        $envBinary = getenv( 'COMPOSER_BINARY' );
        if ( is_string( $envBinary ) && '' !== trim( $envBinary ) ) {
            return $envBinary;
        }

        return self::DEFAULT_COMPOSER_BINARY;
    }
}
