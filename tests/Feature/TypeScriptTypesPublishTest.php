<?php

declare( strict_types=1 );

test( 'cms-types publish tag is registered', function (): void {
    $tags = Illuminate\Support\ServiceProvider::$publishGroups;

    expect( $tags )->toHaveKey( 'cms-types' );
} );

test( 'type definition source files exist', function ( string $file ): void {
    $path = realpath( __DIR__ . '/../../resources/types/' . $file );

    expect( $path )->not()->toBeFalse();
    expect( file_exists( (string) $path ) )->toBeTrue();
} )->with( [
    'index.d.ts',
    'common.d.ts',
    'blog.d.ts',
    'pages.d.ts',
    'content-types.d.ts',
    'users.d.ts',
    'settings.d.ts',
    'notifications.d.ts',
    'plugins.d.ts',
] );

test( 'type definition files are publishable to resource path', function (): void {
    $tags     = Illuminate\Support\ServiceProvider::$publishGroups;
    $mappings = $tags['cms-types'];

    $hasSourcePath      = false;
    $hasDestinationPath = false;

    foreach ( $mappings as $source => $destination ) {
        if ( str_contains( $source, 'resources/types' ) ) {
            $hasSourcePath = true;
        }
        if ( str_contains( $destination, 'types/cms-framework' ) ) {
            $hasDestinationPath = true;
        }
    }

    expect( $hasSourcePath )->toBeTrue()
        ->and( $hasDestinationPath)->toBeTrue();
});
