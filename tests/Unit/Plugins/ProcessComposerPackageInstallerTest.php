<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\ProcessComposerPackageInstaller;

describe( 'ProcessComposerPackageInstaller::redactProcessOutput', function (): void {
    beforeEach( function (): void {
        $this->installer = new ProcessComposerPackageInstaller;
    } );

    it( 'masks inline URL credentials in Composer output', function (): void {
        $output = 'Failed to download from https://alice:s3cr3t-token@repo.example.com/pkg.zip';

        expect( invokeMethod( $this->installer, 'redactProcessOutput', [ $output ] ) )
            ->toContain( 'https://***@repo.example.com/pkg.zip' )
            ->not->toContain( 's3cr3t-token' )
            ->not->toContain( 'alice:' );
    } );

    it( 'masks secret-bearing tokens surfaced in output', function ( string $output, string $secret ): void {
        $redacted = invokeMethod( $this->installer, 'redactProcessOutput', [ $output ] );

        expect( $redacted )->not->toContain( $secret )
            ->and( $redacted )->toContain( '***' );
    } )->with( [
        'token query'    => [ 'GET /packages.json?token=abc123def', 'abc123def' ],
        'api_key header' => [ 'X-Api-Key: super-secret-value', 'super-secret-value' ],
        'password'       => [ 'password=hunter2 was rejected', 'hunter2' ],
    ] );

    it( 'leaves output without secrets untouched', function (): void {
        $output = "Your requirements could not be resolved to an installable set of packages.\nProblem 1: acme/one 1.0 requires php ^8.4";

        expect( invokeMethod( $this->installer, 'redactProcessOutput', [ $output ] ) )
            ->toBe( $output );
    } );
} );
