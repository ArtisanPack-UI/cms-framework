<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Support;

/**
 * Value object describing the outcome of a plugin's Composer-package
 * dependency check.
 *
 * Produced by {@see ComposerPackageResolver::resolve()} and consulted by
 * `PluginManager` when it gates activation on a plugin's manifest `composer`
 * block. Each bucket is empty when that class of problem is absent; a fully
 * satisfied requirement set leaves every bucket empty.
 *
 * This is the Composer-package sibling of {@see DependencyResult}, which covers
 * plugin→plugin dependencies. It intentionally omits the `inactive` and
 * `conflicts` buckets: a Composer package has no activation state, and conflict
 * resolution against the host `composer.lock` is delegated to Composer itself
 * at install time rather than modelled here.
 *
 * @since 2.10.0
 */
class ComposerPackageResult
{
    /**
     * Construct with the two buckets of unmet requirements.
     *
     * @since 2.10.0
     *
     * @param  array<int,array{package:string,required:string}>  $missing  Required packages that are not installed.
     * @param  array<int,array{package:string,required:string,installed:string}>  $versionMismatch  Requirements whose installed version fails the constraint.
     */
    public function __construct(
        public readonly array $missing = [],
        public readonly array $versionMismatch = [],
    ) {
    }

    /**
     * Whether every declared Composer package is installed and in range.
     *
     * @since 2.10.0
     *
     * @return bool True when no problems were recorded.
     */
    public function isSatisfied(): bool
    {
        return empty( $this->missing )
            && empty( $this->versionMismatch );
    }

    /**
     * The `package => constraint` pairs that still need resolving.
     *
     * Combines both buckets into the shape the installer's `install()` method
     * accepts, so a caller can hand exactly the unmet requirements to Composer
     * rather than re-requiring already-satisfied packages.
     *
     * @since 2.10.0
     *
     * @return array<string,string>
     */
    public function unresolvedConstraints(): array
    {
        $constraints = [];

        foreach ( $this->missing as $entry ) {
            $constraints[ $entry['package'] ] = $entry['required'];
        }

        foreach ( $this->versionMismatch as $entry ) {
            $constraints[ $entry['package'] ] = $entry['required'];
        }

        return $constraints;
    }

    /**
     * Render the result as an API-friendly array.
     *
     * Uses the snake_case `version_mismatch` key to match the shape
     * {@see DependencyResult::toArray()} emits for the plugin-dependency graph.
     *
     * @since 2.10.0
     *
     * @return array{satisfied:bool,missing:array<int,array<string,string>>,version_mismatch:array<int,array<string,string>>}
     */
    public function toArray(): array
    {
        return [
            'satisfied'        => $this->isSatisfied(),
            'missing'          => $this->missing,
            'version_mismatch' => $this->versionMismatch,
        ];
    }
}
