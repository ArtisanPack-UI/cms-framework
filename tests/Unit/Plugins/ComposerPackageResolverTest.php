<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\ComposerPackageResolver;

beforeEach( function (): void {
    $this->resolver = new ComposerPackageResolver;
} );

describe( 'ComposerPackageResolver', function (): void {
    it( 'reports satisfaction when every package is installed and in range', function (): void {
        $result = $this->resolver->resolve(
            [ 'artisanpack-ui/convertkit' => '^1.2' ],
            [ 'artisanpack-ui/convertkit' => '1.4.0' ],
        );

        expect( $result->isSatisfied() )->toBeTrue()
            ->and( $result->missing )->toBe( [] )
            ->and( $result->versionMismatch )->toBe( [] );
    } );

    it( 'is satisfied by an empty requirement set', function (): void {
        $result = $this->resolver->resolve( [], [ 'foo/bar' => '1.0.0' ] );

        expect( $result->isSatisfied() )->toBeTrue();
    } );

    it( 'buckets a package that is not installed as missing', function (): void {
        $result = $this->resolver->resolve(
            [ 'artisanpack-ui/convertkit' => '^1.2' ],
            [],
        );

        expect( $result->isSatisfied() )->toBeFalse()
            ->and( $result->missing )->toBe( [
                [ 'package' => 'artisanpack-ui/convertkit', 'required' => '^1.2' ],
            ] )
            ->and( $result->versionMismatch )->toBe( [] );
    } );

    it( 'buckets an installed but out-of-range package as a version mismatch', function (): void {
        $result = $this->resolver->resolve(
            [ 'artisanpack-ui/convertkit' => '^1.2' ],
            [ 'artisanpack-ui/convertkit' => '0.9.0' ],
        );

        expect( $result->isSatisfied() )->toBeFalse()
            ->and( $result->missing )->toBe( [] )
            ->and( $result->versionMismatch )->toBe( [
                [ 'package' => 'artisanpack-ui/convertkit', 'required' => '^1.2', 'installed' => '0.9.0' ],
            ] );
    } );

    it( 'strips a leading v from the installed version before matching', function (): void {
        $result = $this->resolver->resolve(
            [ 'vendor/pkg' => '^2.0' ],
            [ 'vendor/pkg' => 'v2.3.1' ],
        );

        expect( $result->isSatisfied() )->toBeTrue();
    } );

    it( 'treats a dev/branch install as satisfying any constraint', function ( string $installed ): void {
        $result = $this->resolver->resolve(
            [ 'vendor/pkg' => '^1.2' ],
            [ 'vendor/pkg' => $installed ],
        );

        expect( $result->isSatisfied() )->toBeTrue()
            ->and( $result->versionMismatch )->toBe( [] );
    } )->with( [
        'branch alias' => [ 'dev-main' ],
        'x-dev suffix' => [ '1.2.x-dev' ],
    ] );

    it( 'treats an unparseable constraint as unmet rather than throwing', function (): void {
        $result = $this->resolver->resolve(
            [ 'vendor/pkg' => 'not-a-constraint' ],
            [ 'vendor/pkg' => '1.0.0' ],
        );

        expect( $result->isSatisfied() )->toBeFalse()
            ->and( $result->versionMismatch )->toHaveCount( 1 );
    } );

    it( 'collapses both buckets into the unresolved constraint map', function (): void {
        $result = $this->resolver->resolve(
            [ 'vendor/a' => '^1.0', 'vendor/b' => '^2.0' ],
            [ 'vendor/b' => '1.0.0' ],
        );

        expect( $result->unresolvedConstraints() )->toBe( [
            'vendor/a' => '^1.0',
            'vendor/b' => '^2.0',
        ] );
    } );

    it( 'exposes an API-friendly array shape', function (): void {
        $result = $this->resolver->resolve(
            [ 'vendor/a' => '^1.0' ],
            [],
        );

        expect( $result->toArray() )->toBe( [
            'satisfied'        => false,
            'missing'          => [ [ 'package' => 'vendor/a', 'required' => '^1.0' ] ],
            'version_mismatch' => [],
        ] );
    } );
} );
