<?php

declare( strict_types=1 );

/**
 * Guards the config publish tags against README/provider drift.
 *
 * `vendor:publish` exits 0 on a tag that matches nothing, so a documented tag
 * that was never registered fails silently — the consumer gets an empty
 * config/ and no error. These tests assert the umbrella tag exists and that
 * every module's config file is reachable through both it and the module's
 * own tag.
 *
 * @since 2.8.0
 */

use Illuminate\Support\ServiceProvider;

/**
 * Maps each config source file to the destination path it publishes to and
 * the module-only tag that publishes it on its own. Source paths are relative
 * to the package root.
 *
 * @return array<string, array{ source: string, destination: string, tag: string }>
 */
function cmsFrameworkConfigPublishMap(): array
{
    return [
        'framework' => [
            'source'      => 'config/cms-framework.php',
            'destination' => 'artisanpack/cms-framework.php',
            'tag'         => 'artisanpack-package-config',
        ],
        'themes'    => [
            'source'      => 'src/Modules/Themes/config/themes.php',
            'destination' => 'cms/themes.php',
            'tag'         => 'cms-themes-config',
        ],
        'plugins'   => [
            'source'      => 'src/Modules/Plugins/config/plugins.php',
            'destination' => 'cms/plugins.php',
            'tag'         => 'cms-plugins-config',
        ],
        'updates'   => [
            'source'      => 'src/Modules/Core/Updates/config/updates.php',
            'destination' => 'cms/updates.php',
            'tag'         => 'cms-updates-config',
        ],
    ];
}

/**
 * Wraps the publish map for use as a Pest dataset.
 *
 * Pest spreads an array-valued dataset entry across the test's parameters, so
 * each config array is nested one level deeper to arrive as a single argument.
 *
 * @return array<string, array{ 0: array{ source: string, destination: string, tag: string } }>
 */
function cmsFrameworkConfigPublishDataset(): array
{
    return array_map(
        fn ( array $config ): array => [ $config ],
        cmsFrameworkConfigPublishMap(),
    );
}

/**
 * Resolves the destination a tag publishes a given source file to.
 *
 * Providers register sources as unresolved `__DIR__ . '/../...'` strings, so
 * both sides are run through realpath() before comparing.
 *
 * @param  string  $tag     Publish tag to look in.
 * @param  string  $source  Source path relative to the package root.
 *
 * @return string|null The destination path, or null when the tag does not
 *                     publish that source.
 */
function cmsFrameworkPublishDestination( string $tag, string $source ): ?string
{
    $tags = ServiceProvider::$publishGroups;

    if ( ! isset( $tags[ $tag ] ) ) {
        return null;
    }

    $expected = realpath( __DIR__ . '/../../' . $source );

    if ( false === $expected ) {
        return null;
    }

    foreach ( $tags[ $tag ] as $from => $to ) {
        if ( realpath( $from ) === $expected ) {
            return $to;
        }
    }

    return null;
}

test( 'cms-framework-config umbrella publish tag is registered', function (): void {
    expect( ServiceProvider::$publishGroups )->toHaveKey( 'cms-framework-config' );
} );

test( 'umbrella tag publishes every module config file', function ( array $config ): void {
    $destination = cmsFrameworkPublishDestination( 'cms-framework-config', $config['source'] );

    expect( $destination )->not()->toBeNull();
    expect( str_replace( '\\', '/', (string) $destination ) )
        ->toEndWith( $config['destination'] );
} )->with( cmsFrameworkConfigPublishDataset() );

test( 'each module config keeps its own publish tag', function ( array $config ): void {
    $destination = cmsFrameworkPublishDestination( $config['tag'], $config['source'] );

    expect( $destination )->not()->toBeNull();
    expect( str_replace( '\\', '/', (string) $destination ) )
        ->toEndWith( $config['destination'] );
} )->with( cmsFrameworkConfigPublishDataset() );

test( 'every config source file the tags point at exists on disk', function ( array $config ): void {
    $path = realpath( __DIR__ . '/../../' . $config['source'] );

    expect( $path )->not()->toBeFalse();
    expect( is_file( (string) $path ) )->toBeTrue();
} )->with( cmsFrameworkConfigPublishDataset() );

test( 'the readme publishes the tag the providers register', function (): void {
    $readme = file_get_contents( __DIR__ . '/../../README.md' );

    expect( $readme )->not()->toBeFalse();

    preg_match_all( '/--tag=?"?([a-z0-9-]+)"?/i', (string) $readme, $matches );

    $documentedTags = array_unique( $matches[1] );

    expect( $documentedTags )->not()->toBeEmpty();

    foreach ( $documentedTags as $tag ) {
        expect( ServiceProvider::$publishGroups )->toHaveKey( $tag );
    }
} );
