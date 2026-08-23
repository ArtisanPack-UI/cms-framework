<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Contracts;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\ComposerDependencyNotSatisfiedException;

/**
 * Contract for resolving and installing a plugin's declared Composer packages.
 *
 * The default {@see \ArtisanPackUI\CMSFramework\Modules\Plugins\Support\ProcessComposerPackageInstaller}
 * reads installed versions from Composer's runtime metadata and shells out to
 * the `composer` binary to bring a missing package into the host's vendor tree.
 * Extracting the side-effecting parts behind this interface keeps
 * `PluginManager` testable — a test binds a fake installer instead of touching
 * Packagist or the filesystem.
 *
 * @since 2.10.0
 */
interface ComposerPackageInstallerInterface
{
    /**
     * Snapshot the versions of packages currently installed in the host.
     *
     * @since 2.10.0
     *
     * @return array<string,string> Map of package name to installed version.
     */
    public function installedVersions(): array;

    /**
     * Install (or upgrade) the given Composer packages into the host.
     *
     * Implementations must fail closed: any resolution, network, lock-conflict,
     * or process failure has to raise
     * {@see ComposerDependencyNotSatisfiedException} so the caller can unwind a
     * partial activation rather than boot a plugin whose vendor tree is absent.
     *
     * @since 2.10.0
     *
     * @param  string  $slug  The plugin the packages are being installed for (for error context).
     * @param  array<string,string>  $constraints  Map of package name to version constraint.
     *
     * @throws ComposerDependencyNotSatisfiedException When installation cannot complete.
     */
    public function install( string $slug, array $constraints ): void;

    /**
     * The host's regenerated Composer autoload maps, for seating freshly
     * installed classes onto the running class loader before the plugin's
     * service provider boots.
     *
     * Reading the full regenerated maps — not just a declared package's own
     * `composer.json` — is what makes transitive dependencies, classmap
     * entries, and eager `files` helpers autoloadable in the same request that
     * installed them.
     *
     * @since 2.10.0
     *
     * @return array{psr-4:array<string,array<int,string>>,classmap:array<string,string>,files:array<int,string>}
     */
    public function autoloadMaps(): array;
}
