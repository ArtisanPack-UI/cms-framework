<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Support;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\CircularDependencyException;
use Composer\Semver\Semver;
use Throwable;

/**
 * Pure dependency-resolution logic for the plugin system.
 *
 * Operates entirely on a normalized in-memory graph so it can be unit-tested
 * without a database. `PluginManager` builds the graph from installed plugin
 * records and delegates every dependency question here.
 *
 * The graph is an array keyed by plugin slug. Each entry has the shape:
 *
 *     [
 *         'version'       => '1.2.0',            // installed semver
 *         'is_active'     => true,               // activation state
 *         'requires'      => ['other' => '^1.0'], // plugin slug => constraint
 *         'requires_host' => '^2.0',             // cms-framework constraint or null
 *         'conflicts'     => ['legacy' => '*'],  // plugin slug => constraint
 *     ]
 *
 * @since 2.9.0
 */
class DependencyResolver
{
    /**
     * Resolve the dependency state of a single plugin against the graph.
     *
     * @since 2.9.0
     *
     * @param  string  $slug  The plugin whose dependencies are being checked.
     * @param  array<string,array<string,mixed>>  $graph  Installed plugin graph.
     * @param  string|null  $hostVersion  Resolved cms-framework version, or null when unknown.
     *
     * @return DependencyResult The buckets of unmet requirements.
     */
    public function resolve( string $slug, array $graph, ?string $hostVersion ): DependencyResult
    {
        $missing         = [];
        $inactive        = [];
        $versionMismatch = [];
        $conflicts       = [];

        $target = $graph[ $slug ] ?? [];

        // Host-framework constraint. Skipped when the host version cannot be
        // resolved, mirroring the permissive min_host_version policy so a
        // path-repo dev checkout doesn't false-fail every plugin.
        $hostRequirement = $target['requires_host'] ?? null;
        if ( is_string( $hostRequirement ) && null !== $hostVersion
            && ! $this->satisfies( $hostVersion, $hostRequirement ) ) {
            $versionMismatch[] = [
                'slug'      => 'cms-framework',
                'required'  => $hostRequirement,
                'installed' => $hostVersion,
            ];
        }

        foreach ( ( $target['requires'] ?? [] ) as $depSlug => $constraint ) {
            $dependency = $graph[ $depSlug ] ?? null;

            if ( null === $dependency ) {
                $missing[] = $depSlug;

                continue;
            }

            if ( ! $this->satisfies( $dependency['version'], $constraint ) ) {
                $versionMismatch[] = [
                    'slug'      => $depSlug,
                    'required'  => $constraint,
                    'installed' => $dependency['version'],
                ];

                continue;
            }

            if ( empty( $dependency['is_active'] ) ) {
                $inactive[] = $depSlug;
            }
        }

        $conflictsSeen = [];

        // Forward conflicts: those the activating plugin declares.
        foreach ( ( $target['conflicts'] ?? [] ) as $conflictSlug => $constraint ) {
            $other = $graph[ $conflictSlug ] ?? null;

            // A conflict only matters when the other plugin is installed and its
            // version falls within the declared conflict range.
            if ( null !== $other && $this->satisfies( $other['version'], $constraint ) ) {
                $conflicts[]                    = [
                    'slug'       => $conflictSlug,
                    'constraint' => $constraint,
                    'installed'  => $other['version'],
                ];
                $conflictsSeen[ $conflictSlug ] = true;
            }
        }

        // Reverse conflicts: those another installed plugin declares against the
        // target. Without this a conflict is bypassable by activation order —
        // activate the non-declaring plugin first and the two co-exist.
        $targetVersion = $target['version'] ?? null;
        if ( is_string( $targetVersion ) ) {
            foreach ( $graph as $otherSlug => $otherEntry ) {
                if ( $otherSlug === $slug || isset( $conflictsSeen[ $otherSlug ] ) ) {
                    continue;
                }

                $constraint = ( $otherEntry['conflicts'] ?? [] )[ $slug ] ?? null;
                if ( is_string( $constraint ) && $this->satisfies( $targetVersion, $constraint ) ) {
                    $conflicts[] = [
                        'slug'       => $otherSlug,
                        'constraint' => $constraint,
                        'installed'  => $otherEntry['version'],
                    ];
                }
            }
        }

        return new DependencyResult( $missing, $inactive, $versionMismatch, $conflicts );
    }

    /**
     * List the slugs of installed plugins that declare a requirement on $slug.
     *
     * @since 2.9.0
     *
     * @param  string  $slug  The depended-upon plugin.
     * @param  array<string,array<string,mixed>>  $graph  Installed plugin graph.
     *
     * @return array<int,string> Dependent slugs, sorted for a stable order.
     */
    public function dependents( string $slug, array $graph ): array
    {
        $dependents = [];

        foreach ( $graph as $candidate => $entry ) {
            if ( $candidate === $slug ) {
                continue;
            }

            if ( array_key_exists( $slug, $entry['requires'] ?? [] ) ) {
                $dependents[] = $candidate;
            }
        }

        sort( $dependents );

        return $dependents;
    }

    /**
     * Produce a dependency-first activation order for the requested slugs.
     *
     * Performs a depth-first topological sort so every plugin appears after the
     * dependencies it requires. Slugs not present in the graph are skipped.
     *
     * @since 2.9.0
     *
     * @param  array<int,string>  $slugs  Plugins the caller wants to activate.
     * @param  array<string,array<string,mixed>>  $graph  Installed plugin graph.
     *
     * @throws CircularDependencyException When a dependency cycle is detected.
     *
     * @return array<int,string> Slugs ordered dependencies-first.
     */
    public function activationOrder( array $slugs, array $graph ): array
    {
        $order   = [];
        $visited = [];

        foreach ( $slugs as $slug ) {
            if ( isset( $graph[ $slug ] ) ) {
                $this->visit( $slug, $graph, $order, $visited, [] );
            }
        }

        return $order;
    }

    /**
     * Whether an installed version satisfies a semver constraint.
     *
     * Wraps composer/semver, treating any unparseable version or constraint as
     * an unmet requirement rather than letting the exception escape.
     *
     * @since 2.9.0
     *
     * @param  mixed  $version  Installed version (a non-string counts as unmet).
     * @param  mixed  $constraint  Declared constraint (e.g. "^1.0", "*").
     *
     * @return bool True when the version satisfies the constraint.
     */
    protected function satisfies( mixed $version, mixed $constraint ): bool
    {
        // A graph entry missing a string version — allowed by the documented
        // public graph contract — is an unmet requirement, not a TypeError.
        if ( ! is_string( $version ) || ! is_string( $constraint ) ) {
            return false;
        }

        $normalized = ltrim( $version, 'vV' );

        try {
            return Semver::satisfies( $normalized, $constraint );
        } catch ( Throwable $e ) {
            return false;
        }
    }

    /**
     * Recursive DFS visit used by {@see activationOrder()}.
     *
     * @since 2.9.0
     *
     * @param  string  $slug  Node being visited.
     * @param  array<string,array<string,mixed>>  $graph  Installed plugin graph.
     * @param  array<int,string>  $order  Accumulating post-order list (by reference).
     * @param  array<string,bool>  $visited  Finalized nodes (by reference).
     * @param  array<int,string>  $path  Current DFS stack for cycle detection.
     *
     * @throws CircularDependencyException When $slug reappears on the active path.
     */
    private function visit( string $slug, array $graph, array &$order, array &$visited, array $path ): void
    {
        if ( isset( $visited[ $slug ] ) ) {
            return;
        }

        if ( in_array( $slug, $path, true ) ) {
            $cycleStart = ( int ) array_search( $slug, $path, true );
            $cycle      = array_slice( $path, $cycleStart );
            $cycle[]    = $slug;

            throw CircularDependencyException::forCycle( $cycle );
        }

        $path[] = $slug;

        foreach ( array_keys( $graph[ $slug ]['requires'] ?? [] ) as $dependency ) {
            if ( isset( $graph[ $dependency ] ) ) {
                $this->visit( ( string ) $dependency, $graph, $order, $visited, $path );
            }
        }

        $visited[ $slug ] = true;
        $order[]          = $slug;
    }
}
