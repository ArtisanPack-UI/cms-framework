<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Support;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Contracts\ComposerPackageInstallerInterface;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\ComposerDependencyNotSatisfiedException;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
        // A zero or negative value disables the process timeout entirely, but the
        // serialization lock's TTL is derived from it (`$timeout + 30`). Left
        // unclamped, an unbounded `composer require` would run behind a lock that
        // expires after 30s, letting a second activation start a concurrent
        // require in the same directory — the exact corruption the lock prevents.
        if ( $timeout <= 0 ) {
            $timeout = 600;
        }
        // `--no-plugins` keeps a malicious dependency of type `composer-plugin`
        // from executing its own code during the require — the marginal
        // supply-chain surface this feature introduces. Scripts are deliberately
        // left enabled: the host's `post-autoload-dump` runs `package:discover`,
        // which is how an installed Laravel package's own provider is
        // auto-registered.
        $command = $binary . ' require ' . implode( ' ', $specs ) . ' --no-interaction --no-progress --no-plugins --with-all-dependencies';

        // Auto-install runs third-party code in the host process, so leave an
        // audit trail of exactly what was required, for which plugin. The specs
        // are validated package:constraint pairs — no secrets — and the binary
        // is operator-configured.
        Log::info( 'cms-framework: installing plugin Composer dependencies.', [
            'plugin'   => $slug,
            'packages' => array_keys( $constraints ),
            'command'  => $command,
        ] );

        // `composer require` rewrites the host `composer.json`/`composer.lock`
        // in place; two overlapping activations in that directory can drop a
        // requirement or leave the lock inconsistent. Serialize the run behind
        // an atomic lock and fail closed when another install holds it.
        $lock = Cache::lock( 'cms.plugins.composer-require', $timeout + 30 );

        if ( ! $lock->get() ) {
            throw ComposerDependencyNotSatisfiedException::installationFailed(
                $slug,
                'Another Composer installation is already running; try again once it completes.',
            );
        }

        try {
            $result = Process::timeout( $timeout )
                ->path( base_path() )
                ->run( $command );
        } catch ( ComposerDependencyNotSatisfiedException $e ) {
            throw $e;
        } catch ( Throwable $e ) {
            throw ComposerDependencyNotSatisfiedException::installationFailed( $slug, $e->getMessage() );
        } finally {
            $lock->release();
        }

        if ( ! $result->successful() ) {
            $reason = '' !== trim( $result->errorOutput() )
                ? trim( $result->errorOutput() )
                : trim( $result->output() );

            throw ComposerDependencyNotSatisfiedException::installationFailed(
                $slug,
                '' !== $reason ? $this->redactProcessOutput( $reason ) : 'composer require exited with a non-zero status.',
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
     *
     * @return array{psr-4:array<string,array<int,string>>,classmap:array<string,string>,files:array<int,string>}
     */
    public function autoloadMaps(): array
    {
        $base = base_path( 'vendor/composer/' );

        return [
            'psr-4'    => $this->readAutoloadMap( $base . 'autoload_psr4.php' ),
            'classmap' => $this->readAutoloadMap( $base . 'autoload_classmap.php' ),
            'files'    => array_values( $this->readAutoloadMap( $base . 'autoload_files.php' ) ),
        ];
    }

    /**
     * Read one of Composer's regenerated autoload map files, invalidating any
     * stale OPcache copy first so the just-installed entries are seen.
     *
     * @since 2.10.0
     *
     * @param  string  $path  Absolute path to the map file.
     *
     * @return array<int|string,mixed> The map, or an empty array on any failure.
     */
    protected function readAutoloadMap( string $path ): array
    {
        try {
            if ( ! is_file( $path ) ) {
                return [];
            }

            if ( function_exists( 'opcache_invalidate' ) ) {
                opcache_invalidate( $path, true );
            }

            $data = require $path;

            return is_array( $data ) ? $data : [];
        } catch ( Throwable $e ) {
            return [];
        }
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
     * Redact secrets from Composer's process output before it is surfaced in an
     * activation error (shown to admins, written to logs).
     *
     * A failed `composer require` against a private repository can echo the
     * tokenised URL it tried (`https://user:token@host/…`) or an
     * auth/token query parameter. Strip inline URL credentials and common
     * secret-bearing query keys so the failure detail stays useful without
     * leaking them.
     *
     * @since 2.10.0
     *
     * @param  string  $output  Raw process output.
     *
     * @return string The output with credentials masked.
     */
    protected function redactProcessOutput( string $output ): string
    {
        $output = (string) preg_replace( '#(://)[^/@\s:]+:[^/@\s]+@#', '$1***@', $output );
        $output = (string) preg_replace( '#(?i)(token|password|passwd|secret|api[_-]?key|authorization)([=:]\s*)\S+#', '$1$2***', $output );

        return $output;
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
