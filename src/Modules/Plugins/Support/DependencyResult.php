<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Support;

/**
 * Value object describing the outcome of a plugin dependency check.
 *
 * Produced by {@see DependencyResolver::resolve()} and surfaced through
 * `PluginManager::checkDependencies()` and the dependency API endpoints. Each
 * bucket is empty when that class of problem is absent; a fully satisfied
 * dependency set leaves every bucket empty.
 *
 * @since 2.9.0
 */
class DependencyResult
{
    /**
     * Construct with the four buckets of unmet requirements.
     *
     * @since 2.9.0
     *
     * @param  array<int,string>  $missing  Required plugin slugs that are not installed.
     * @param  array<int,string>  $inactive  Required plugin slugs installed but not active.
     * @param  array<int,array{slug:string,required:string,installed:string}>  $versionMismatch  Requirements whose installed version fails the constraint.
     * @param  array<int,array{slug:string,constraint:string,installed:string}>  $conflicts  Installed plugins that match a declared conflict.
     */
    public function __construct(
        public readonly array $missing = [],
        public readonly array $inactive = [],
        public readonly array $versionMismatch = [],
        public readonly array $conflicts = [],
    ) {
    }

    /**
     * Whether every dependency, version constraint, and conflict rule is met.
     *
     * @since 2.9.0
     *
     * @return bool True when no problems were recorded.
     */
    public function isSatisfied(): bool
    {
        return empty( $this->missing )
            && empty( $this->inactive )
            && empty( $this->versionMismatch )
            && empty( $this->conflicts );
    }

    /**
     * Render the result as an API-friendly array.
     *
     * Uses the snake_case `version_mismatch` key from the issue spec so API
     * consumers receive the documented shape.
     *
     * @since 2.9.0
     *
     * @return array{satisfied:bool,missing:array<int,string>,inactive:array<int,string>,version_mismatch:array<int,array<string,string>>,conflicts:array<int,array<string,string>>}
     */
    public function toArray(): array
    {
        return [
            'satisfied'        => $this->isSatisfied(),
            'missing'          => $this->missing,
            'inactive'         => $this->inactive,
            'version_mismatch' => $this->versionMismatch,
            'conflicts'        => $this->conflicts,
        ];
    }
}
