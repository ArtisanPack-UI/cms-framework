<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'version',
        'is_active',
        'service_provider',
        'meta',
        'installed_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'meta'         => 'array',
        'installed_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    /**
     * Scope to get only active plugins.
     */
    public function scopeActive( Builder $query )
    {
        return $query->where( 'is_active', true );
    }

    /**
     * Get plugin path on filesystem.
     */
    public function getPath(): string
    {
        return base_path( config( 'cms.plugins.directory', 'plugins' ) . '/' . $this->slug );
    }

    /**
     * Get plugin manifest data.
     */
    public function getManifest(): array
    {
        return $this->meta ?? [];
    }

    /**
     * Check if plugin has a service provider.
     */
    public function hasServiceProvider(): bool
    {
        return ! empty( $this->service_provider );
    }

    /**
     * Minimum cms-framework version this plugin declares support for.
     *
     * Reads `min_framework_version`, falling back to the deprecated
     * `min_host_version` alias when only the old key is declared.
     *
     * @since 2.14.0
     */
    public function getMinFrameworkVersionAttribute(): ?string
    {
        foreach ( ['min_framework_version', 'min_host_version'] as $key ) {
            $value = $this->meta[ $key ] ?? null;
            if ( is_string( $value ) ) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Minimum cms-framework version this plugin declares support for.
     *
     * @deprecated 2.14.0 Use `min_framework_version`. Despite its name, this
     *             has always been compared against the cms-framework version,
     *             not the host application's version.
     */
    public function getMinHostVersionAttribute(): ?string
    {
        return $this->min_framework_version;
    }

    /**
     * Minimum host-application version this plugin declares support for.
     *
     * Compared against `cms.plugins.appVersion`, which the host application
     * (e.g. Keystone CMS) supplies.
     *
     * @since 2.14.0
     */
    public function getMinAppVersionAttribute(): ?string
    {
        $value = $this->meta['min_app_version'] ?? null;

        return is_string( $value ) ? $value : null;
    }

    /**
     * Federated frontend module descriptor from the manifest.
     *
     * @return array{entry:string,exposes?:array<int,string>}|null
     */
    public function getFederatedModuleAttribute(): ?array
    {
        $module = $this->meta['federated_module'] ?? null;

        return is_array( $module ) ? $module : null;
    }

    /**
     * Nav entries pre-declared by the plugin manifest.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getNavEntriesAttribute(): array
    {
        $entries = $this->meta['nav_entries'] ?? [];

        return is_array( $entries ) ? array_values( $entries ) : [];
    }

    /**
     * Permission slugs the plugin wants seeded on activation.
     *
     * @return array<int,string>
     */
    public function getDeclaredPermissionsAttribute(): array
    {
        $permissions = $this->meta['permissions'] ?? [];
        if ( ! is_array( $permissions ) ) {
            return [];
        }

        return array_values( array_filter( $permissions, 'is_string' ) );
    }

    /**
     * Whether the plugin has opted into migration rollback on deletion.
     */
    public function getRollbackMigrationsOnDeleteAttribute(): bool
    {
        return ( bool ) ( $this->meta['rollback_migrations_on_delete'] ?? false );
    }

    /**
     * Invokable uninstall class declared under the manifest `uninstall` key (#358).
     *
     * @since 2.13.0
     *
     * @return string|null Fully-qualified class name, or null when none is declared.
     */
    public function getUninstallerAttribute(): ?string
    {
        $value = $this->meta['uninstall'] ?? null;

        return is_string( $value ) && '' !== $value ? $value : null;
    }

    /**
     * Plugin dependencies declared under `requires.plugins`.
     *
     * @return array<string,string> Map of dependency slug to version constraint.
     */
    public function getRequiredPluginsAttribute(): array
    {
        $requires = $this->meta['requires']['plugins'] ?? [];

        return $this->normalizeConstraintMap( $requires );
    }

    /**
     * Framework version constraint declared under `requires.cms-framework`.
     */
    public function getRequiredHostVersionAttribute(): ?string
    {
        $value = $this->meta['requires']['cms-framework'] ?? null;

        return is_string( $value ) ? $value : null;
    }

    /**
     * Conflicting plugins declared under `conflicts`.
     *
     * @return array<string,string> Map of conflicting slug to version constraint.
     */
    public function getConflictingPluginsAttribute(): array
    {
        return $this->normalizeConstraintMap( $this->meta['conflicts'] ?? [] );
    }

    /**
     * Composer packages declared under the manifest `composer` block (#323).
     *
     * @return array<string,string> Map of Composer package name to version constraint.
     */
    public function getRequiredComposerPackagesAttribute(): array
    {
        return $this->normalizeConstraintMap( $this->meta['composer'] ?? [] );
    }

    /**
     * Reduce a raw manifest constraint map to string-keyed, string-valued
     * entries so downstream resolution never trips over malformed input.
     *
     * @param  mixed  $map  Raw value from the manifest.
     *
     * @return array<string,string>
     */
    private function normalizeConstraintMap( mixed $map ): array
    {
        if ( ! is_array( $map ) ) {
            return [];
        }

        $normalized = [];
        foreach ( $map as $slug => $constraint ) {
            if ( is_string( $slug ) && is_string( $constraint ) ) {
                $normalized[ $slug ] = $constraint;
            }
        }

        return $normalized;
    }
}
