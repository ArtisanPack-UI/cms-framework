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
     * Minimum framework version this plugin declares support for.
     */
    public function getMinHostVersionAttribute(): ?string
    {
        $value = $this->meta['min_host_version'] ?? null;

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
