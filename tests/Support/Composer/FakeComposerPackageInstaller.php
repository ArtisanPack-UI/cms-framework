<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Support\Composer;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Contracts\ComposerPackageInstallerInterface;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\ComposerDependencyNotSatisfiedException;

/**
 * In-memory {@see ComposerPackageInstallerInterface} for tests: it models the
 * host's installed packages without touching Composer, Packagist, or the
 * filesystem, so the activation gate (#323) can be exercised deterministically.
 */
class FakeComposerPackageInstaller implements ComposerPackageInstallerInterface
{
    /**
     * Currently "installed" packages, keyed by name.
     *
     * @var array<string,string>
     */
    public array $installed = [];

    /**
     * Versions applied to `$installed` when a package is installed, keyed by
     * name. A package absent here is not resolved by a simulated install.
     *
     * @var array<string,string>
     */
    public array $installsToApply = [];

    /**
     * Install paths returned by installPath(), keyed by package name.
     *
     * @var array<string,string>
     */
    public array $installPaths = [];

    /**
     * When true, install() throws instead of resolving anything.
     */
    public bool $failInstall = false;

    /**
     * Reason surfaced when $failInstall is true.
     */
    public ?string $failReason = null;

    /**
     * Recorded install() invocations, for assertions.
     *
     * @var array<int,array{slug:string,constraints:array<string,string>}>
     */
    public array $installCalls = [];

    /**
     * @return array<string,string>
     */
    public function installedVersions(): array
    {
        return $this->installed;
    }

    /**
     * @param  array<string,string>  $constraints
     */
    public function install( string $slug, array $constraints ): void
    {
        $this->installCalls[] = [ 'slug' => $slug, 'constraints' => $constraints ];

        if ( $this->failInstall ) {
            throw ComposerDependencyNotSatisfiedException::installationFailed(
                $slug,
                $this->failReason ?? 'simulated install failure',
            );
        }

        foreach ( array_keys( $constraints ) as $package ) {
            if ( isset( $this->installsToApply[ $package ] ) ) {
                $this->installed[ $package ] = $this->installsToApply[ $package ];
            }
        }
    }

    public function installPath( string $package ): ?string
    {
        return $this->installPaths[ $package ] ?? null;
    }
}
