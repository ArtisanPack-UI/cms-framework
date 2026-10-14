<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Support;

/**
 * Container-bound singleton that holds runtime registrations made by plugin
 * service providers (nav entries and federated module descriptors).
 *
 * Plugin providers push into this registry from boot(); the framework wires a
 * single `ap.cmsFramework.admin.menu` filter callback in PluginsServiceProvider that reads
 * from it. This avoids adding a fresh closure to the filter chain on every
 * request under Octane / RoadRunner (where the container persists across
 * requests but boot() re-runs).
 *
 * @since 2.4.0
 */
class PluginRegistry
{
    /**
     * Nav entries keyed by slug so duplicate registrations idempotently
     * overwrite instead of accumulating.
     *
     * @var array<string,array<string,mixed>>
     */
    protected array $navEntries = [];

    /**
     * Federated module descriptors keyed by name.
     *
     * @var array<string,array{entry:string,exposes?:array<int,string>,bootModule?:string}>
     */
    protected array $federatedModules = [];

    /**
     * Public asset directories keyed by plugin slug, served by the plugin
     * asset route.
     *
     * @var array<string,array{directory:string,entry:string}>
     */
    protected array $assetDirectories = [];

    /**
     * Register (or overwrite) a nav entry by slug.
     *
     * @param  array<string,mixed>  $entry  A normalized nav entry row.
     */
    public function setNavEntry( string $slug, array $entry ): void
    {
        $this->navEntries[ $slug ] = $entry;
    }

    /**
     * All registered nav entries.
     *
     * @return array<string,array<string,mixed>>
     */
    public function navEntries(): array
    {
        return $this->navEntries;
    }

    /**
     * Register (or overwrite) a federated module descriptor by name.
     *
     * @param  array{entry:string,exposes?:array<int,string>,bootModule?:string}  $descriptor
     */
    public function setFederatedModule( string $name, array $descriptor ): void
    {
        $this->federatedModules[ $name ] = $descriptor;
    }

    /**
     * All registered federated module descriptors.
     *
     * @return array<string,array{entry:string,exposes?:array<int,string>,bootModule?:string}>
     */
    public function federatedModules(): array
    {
        return $this->federatedModules;
    }

    /**
     * Expose a plugin directory through the plugin asset route.
     *
     * Only directories registered here are ever served, which also limits the
     * route to plugins whose provider booted ( active plugins ).
     *
     * @since 2.14.0
     *
     * @param  string  $slug  The plugin slug.
     * @param  string  $directory  Absolute path of the directory to serve.
     * @param  string  $entry  File name of the federation entry inside it, served with a revalidating cache policy.
     */
    public function setAssetDirectory( string $slug, string $directory, string $entry ): void
    {
        $this->assetDirectories[ $slug ] = [
            'directory' => $directory,
            'entry'     => $entry,
        ];
    }

    /**
     * The asset directory registered for a plugin, if any.
     *
     * @since 2.14.0
     *
     * @param  string  $slug  The plugin slug.
     *
     * @return array{directory:string,entry:string}|null
     */
    public function assetDirectory( string $slug ): ?array
    {
        return $this->assetDirectories[ $slug ] ?? null;
    }
}
