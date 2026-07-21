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
     * @var array<string,array{entry:string,exposes?:array<int,string>}>
     */
    protected array $federatedModules = [];

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
     * @param  array{entry:string,exposes?:array<int,string>}  $descriptor
     */
    public function setFederatedModule( string $name, array $descriptor ): void
    {
        $this->federatedModules[ $name ] = $descriptor;
    }

    /**
     * All registered federated module descriptors.
     *
     * @return array<string,array{entry:string,exposes?:array<int,string>}>
     */
    public function federatedModules(): array
    {
        return $this->federatedModules;
    }
}
