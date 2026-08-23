<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\ComposerPackageResult;

/**
 * Raised when a plugin cannot be activated because its declared Composer
 * packages (the manifest `composer` block) are missing or fail their version
 * constraints, or because installing them could not complete.
 *
 * The Composer-package sibling of {@see DependencyNotSatisfiedException}
 * (plugin→plugin). Fails closed so a plugin is never booted with an absent
 * vendor tree — the activation transaction unwinds, and the update flow
 * restores the previous version through its backup-restore path.
 *
 * @since 2.10.0
 */
class ComposerDependencyNotSatisfiedException extends CMSFrameworkException
{
    /**
     * Slug of the plugin whose Composer requirements could not be satisfied.
     *
     * @since 2.10.0
     */
    public readonly string $pluginSlug;

    /**
     * The dependency result when the failure came from an unsatisfied
     * resolution. Null when the failure came from the installer itself.
     *
     * @since 2.10.0
     */
    public readonly ?ComposerPackageResult $result;

    /**
     * Construct with pre-resolved Composer dependency metadata.
     *
     * @since 2.10.0
     *
     * @param  string  $message  Human-readable failure message.
     * @param  string  $slug  Plugin slug the failure concerns.
     * @param  ComposerPackageResult|null  $result  Unsatisfied resolution result, if any.
     */
    public function __construct( string $message, string $slug, ?ComposerPackageResult $result = null )
    {
        parent::__construct( $message );
        $this->pluginSlug = $slug;
        $this->result     = $result;
    }

    /**
     * Build the exception for an unsatisfied Composer requirement set.
     *
     * @since 2.10.0
     *
     * @param  string  $slug  Plugin being activated.
     * @param  ComposerPackageResult  $result  The unsatisfied resolution result.
     *
     * @return self
     */
    public static function forResult( string $slug, ComposerPackageResult $result ): self
    {
        return new self(
            "Plugin '{$slug}' cannot be activated because its required Composer packages are not satisfied.",
            $slug,
            $result,
        );
    }

    /**
     * Build the exception for an installer failure (Packagist unreachable, a
     * lock conflict with the host's `composer.lock`, a missing `composer`
     * binary, or a non-zero install exit).
     *
     * @since 2.10.0
     *
     * @param  string  $slug  Plugin being activated.
     * @param  string  $reason  The underlying failure detail.
     *
     * @return self
     */
    public static function installationFailed( string $slug, string $reason ): self
    {
        return new self(
            "Plugin '{$slug}' could not install its required Composer packages: {$reason}",
            $slug,
        );
    }

    /**
     * Build the exception raised when a plugin's declared Composer packages name
     * one the host itself requires in its root `composer.json`.
     *
     * Refused before shelling out to Composer, so a plugin can never change the
     * installed version of a library the host owns.
     *
     * @since 2.10.0
     *
     * @param  string  $slug  Plugin being activated.
     * @param  array<int,string>  $packages  Host root packages the plugin tried to require.
     *
     * @return self
     */
    public static function rootPackageConflict( string $slug, array $packages ): self
    {
        $list = implode( ', ', $packages );

        return new self(
            "Plugin '{$slug}' declares Composer dependencies the host already requires and may not manage: {$list}. Remove them from the plugin manifest, or align the host's own requirement.",
            $slug,
        );
    }

    /**
     * Build the exception raised when Composer installed the packages (exit
     * zero) but this process still cannot see them — the OPcache-restricted
     * host case where the in-process autoload / InstalledVersions snapshot stays
     * stale. Fails closed; a fresh request completes the activation.
     *
     * @since 2.10.0
     *
     * @param  string  $slug  Plugin being activated.
     * @param  ComposerPackageResult  $result  The still-unsatisfied resolution result.
     *
     * @return self
     */
    public static function installedButUnverified( string $slug, ComposerPackageResult $result ): self
    {
        return new self(
            "Plugin '{$slug}' installed its Composer packages, but this process cannot verify them yet (the host likely restricts OPcache invalidation). Retry the activation — a fresh request will pick up the installed packages.",
            $slug,
            $result,
        );
    }
}
