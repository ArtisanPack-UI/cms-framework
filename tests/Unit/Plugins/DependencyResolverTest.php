<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\CircularDependencyException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\DependencyResolver;

beforeEach( function (): void {
    $this->resolver = new DependencyResolver;
} );

/**
 * Build a graph entry with sensible defaults.
 */
function graphEntry( array $overrides = [] ): array
{
    return array_merge( [
        'version'       => '1.0.0',
        'is_active'     => true,
        'requires'      => [],
        'requires_host' => null,
        'conflicts'     => [],
    ], $overrides );
}

describe( 'resolve', function (): void {
    it( 'reports a fully satisfied plugin', function (): void {
        $graph = [
            'base'  => graphEntry( ['version' => '1.5.0'] ),
            'child' => graphEntry( ['requires' => ['base' => '^1.0']] ),
        ];

        $result = $this->resolver->resolve( 'child', $graph, '2.9.0' );

        expect( $result->isSatisfied() )->toBeTrue()
            ->and( $result->missing )->toBe( [] )
            ->and( $result->inactive )->toBe( [] )
            ->and( $result->versionMismatch )->toBe( [] )
            ->and( $result->conflicts )->toBe( [] );
    } );

    it( 'reports a missing dependency', function (): void {
        $graph = [
            'child' => graphEntry( ['requires' => ['base' => '^1.0']] ),
        ];

        $result = $this->resolver->resolve( 'child', $graph, '2.9.0' );

        expect( $result->isSatisfied() )->toBeFalse()
            ->and( $result->missing )->toBe( ['base'] );
    } );

    it( 'treats an unparseable constraint as an unsatisfied requirement, not a fatal', function (): void {
        $graph = [
            'base'  => graphEntry( ['version' => '1.0.0'] ),
            'child' => graphEntry( ['requires' => ['base' => '^not^a^constraint']] ),
        ];

        $result = $this->resolver->resolve( 'child', $graph, '2.9.0' );

        expect( $result->isSatisfied() )->toBeFalse()
            ->and( $result->versionMismatch )->toBe( [
                ['slug' => 'base', 'required' => '^not^a^constraint', 'installed' => '1.0.0'],
            ] );
    } );

    it( 'normalizes a v-prefixed installed version before matching the constraint', function (): void {
        $graph = [
            'base'  => graphEntry( ['version' => 'v1.5.0'] ),
            'child' => graphEntry( ['requires' => ['base' => '^1.0']] ),
        ];

        $result = $this->resolver->resolve( 'child', $graph, '2.9.0' );

        expect( $result->isSatisfied() )->toBeTrue();
    } );

    it( 'reports an installed but inactive dependency', function (): void {
        $graph = [
            'base'  => graphEntry( ['is_active' => false] ),
            'child' => graphEntry( ['requires' => ['base' => '^1.0']] ),
        ];

        $result = $this->resolver->resolve( 'child', $graph, '2.9.0' );

        expect( $result->inactive )->toBe( ['base'] )
            ->and( $result->missing )->toBe( [] );
    } );

    it( 'reports a version mismatch and does not also flag it inactive', function (): void {
        $graph = [
            'base'  => graphEntry( ['version' => '1.0.0', 'is_active' => false] ),
            'child' => graphEntry( ['requires' => ['base' => '^2.0']] ),
        ];

        $result = $this->resolver->resolve( 'child', $graph, '2.9.0' );

        expect( $result->inactive )->toBe( [] )
            ->and( $result->versionMismatch )->toBe( [
                ['slug' => 'base', 'required' => '^2.0', 'installed' => '1.0.0'],
            ] );
    } );

    it( 'reports a matched conflict', function (): void {
        $graph = [
            'legacy' => graphEntry( ['version' => '3.2.0'] ),
            'child'  => graphEntry( ['conflicts' => ['legacy' => '*']] ),
        ];

        $result = $this->resolver->resolve( 'child', $graph, '2.9.0' );

        expect( $result->conflicts )->toBe( [
            ['slug' => 'legacy', 'constraint' => '*', 'installed' => '3.2.0'],
        ] );
    } );

    it( 'ignores a conflict whose version is out of range', function (): void {
        $graph = [
            'legacy' => graphEntry( ['version' => '2.0.0'] ),
            'child'  => graphEntry( ['conflicts' => ['legacy' => '^1.0']] ),
        ];

        $result = $this->resolver->resolve( 'child', $graph, '2.9.0' );

        expect( $result->conflicts )->toBe( [] )
            ->and( $result->isSatisfied() )->toBeTrue();
    } );

    it( 'ignores a conflict for a plugin that is not installed', function (): void {
        $graph = [
            'child' => graphEntry( ['conflicts' => ['legacy' => '*']] ),
        ];

        $result = $this->resolver->resolve( 'child', $graph, '2.9.0' );

        expect( $result->conflicts )->toBe( [] );
    } );

    it( 'detects a reverse conflict declared by another installed plugin', function (): void {
        // `legacy` declares nothing, but `advanced` conflicts with it — so
        // activating `legacy` while `advanced` is installed must still conflict.
        $graph = [
            'advanced' => graphEntry( ['conflicts' => ['legacy' => '*']] ),
            'legacy'   => graphEntry( ['version' => '1.0.0'] ),
        ];

        $result = $this->resolver->resolve( 'legacy', $graph, '2.9.0' );

        expect( $result->conflicts )->toBe( [
            ['slug' => 'advanced', 'constraint' => '*', 'installed' => '1.0.0'],
        ] );
    } );

    it( 'does not double-report a mutually-declared conflict', function (): void {
        $graph = [
            'a' => graphEntry( ['version' => '1.0.0', 'conflicts' => ['b' => '*']] ),
            'b' => graphEntry( ['version' => '1.0.0', 'conflicts' => ['a' => '*']] ),
        ];

        $result = $this->resolver->resolve( 'a', $graph, '2.9.0' );

        expect( $result->conflicts )->toBe( [
            ['slug' => 'b', 'constraint' => '*', 'installed' => '1.0.0'],
        ] );
    } );

    it( 'ignores a reverse conflict whose constraint excludes the target version', function (): void {
        $graph = [
            'advanced' => graphEntry( ['conflicts' => ['legacy' => '^2.0']] ),
            'legacy'   => graphEntry( ['version' => '1.0.0'] ),
        ];

        $result = $this->resolver->resolve( 'legacy', $graph, '2.9.0' );

        expect( $result->conflicts )->toBe( [] );
    } );

    it( 'flags a host framework version below the requirement', function (): void {
        $graph = [
            'child' => graphEntry( ['requires_host' => '^3.0'] ),
        ];

        $result = $this->resolver->resolve( 'child', $graph, '2.9.0' );

        expect( $result->versionMismatch )->toBe( [
            ['slug' => 'cms-framework', 'required' => '^3.0', 'installed' => '2.9.0'],
        ] );
    } );

    it( 'skips the host check when the host version is unknown', function (): void {
        $graph = [
            'child' => graphEntry( ['requires_host' => '^3.0'] ),
        ];

        $result = $this->resolver->resolve( 'child', $graph, null );

        expect( $result->isSatisfied() )->toBeTrue();
    } );
} );

describe( 'dependents', function (): void {
    it( 'lists plugins that require the given slug, sorted', function (): void {
        $graph = [
            'base'  => graphEntry(),
            'zeta'  => graphEntry( ['requires' => ['base' => '^1.0']] ),
            'alpha' => graphEntry( ['requires' => ['base' => '^1.0']] ),
            'other' => graphEntry( ['requires' => ['unrelated' => '^1.0']] ),
        ];

        expect( $this->resolver->dependents( 'base', $graph ) )->toBe( ['alpha', 'zeta'] );
    } );

    it( 'returns an empty array when nothing depends on the slug', function (): void {
        $graph = ['base' => graphEntry()];

        expect( $this->resolver->dependents( 'base', $graph ) )->toBe( [] );
    } );
} );

describe( 'activationOrder', function (): void {
    it( 'orders dependencies before dependents', function (): void {
        $graph = [
            'a' => graphEntry( ['requires' => ['b' => '^1.0']] ),
            'b' => graphEntry( ['requires' => ['c' => '^1.0']] ),
            'c' => graphEntry(),
        ];

        expect( $this->resolver->activationOrder( ['a'], $graph ) )->toBe( ['c', 'b', 'a'] );
    } );

    it( 'skips slugs that are not installed', function (): void {
        $graph = ['a' => graphEntry()];

        expect( $this->resolver->activationOrder( ['a', 'ghost'], $graph ) )->toBe( ['a'] );
    } );

    it( 'throws on a direct dependency cycle', function (): void {
        $graph = [
            'a' => graphEntry( ['requires' => ['b' => '^1.0']] ),
            'b' => graphEntry( ['requires' => ['a' => '^1.0']] ),
        ];

        expect( fn () => $this->resolver->activationOrder( ['a'], $graph ) )
            ->toThrow( CircularDependencyException::class );
    } );

    it( 'exposes the offending cycle path', function (): void {
        $graph = [
            'a' => graphEntry( ['requires' => ['b' => '^1.0']] ),
            'b' => graphEntry( ['requires' => ['a' => '^1.0']] ),
        ];

        try {
            $this->resolver->activationOrder( ['a'], $graph );
            $this->fail( 'Expected CircularDependencyException.' );
        } catch ( CircularDependencyException $e ) {
            expect( $e->cycle )->toBe( ['a', 'b', 'a'] );
        }
    } );
} );
