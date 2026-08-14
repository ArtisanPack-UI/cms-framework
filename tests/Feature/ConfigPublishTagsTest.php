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
        'framework' => cmsFrameworkConfigEntry(
            'config/cms-framework.php',
            'artisanpack/cms-framework.php',
            'artisanpack-package-config',
        ),
        'themes' => cmsFrameworkConfigEntry(
            'src/Modules/Themes/config/themes.php',
            'cms/themes.php',
            'cms-themes-config',
        ),
        'plugins' => cmsFrameworkConfigEntry(
            'src/Modules/Plugins/config/plugins.php',
            'cms/plugins.php',
            'cms-plugins-config',
        ),
        'updates' => cmsFrameworkConfigEntry(
            'src/Modules/Core/Updates/config/updates.php',
            'cms/updates.php',
            'cms-updates-config',
        ),
    ];
}

/**
 * Builds one entry of the publish map.
 *
 * @param  string  $source       Source path relative to the package root.
 * @param  string  $destination  Tail of the path the file publishes to.
 * @param  string  $tag          Tag that publishes this file on its own.
 *
 * @return array{ source: string, destination: string, tag: string }
 */
function cmsFrameworkConfigEntry( string $source, string $destination, string $tag ): array
{
    return compact( 'source', 'destination', 'tag' );
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

/**
 * Lists the publish tags that resolve to a file inside this package.
 *
 * `ServiceProvider::$publishGroups` is a global static that accumulates every
 * provider the TestCase boots — Security, Rbac, Sanctum, Scramble, Hooks, Ai —
 * so a bare `toHaveKey()` against it proves only that *some* provider claimed
 * the tag. Ownership is therefore decided by realpath prefix.
 *
 * A plain package-root prefix is not enough: this package's own `vendor/` lives
 * under that root, so every dependency's publishes would match it and the
 * filter would be a no-op. Matching the source directories a first-party
 * publish can legitimately come from keeps the check meaningful.
 *
 * @return array<int, string>
 */
function cmsFrameworkOwnedPublishTags(): array
{
    $packageRoot = realpath( __DIR__ . '/../../' );
    $ownedRoots  = array_filter( [
        realpath( $packageRoot . '/src' ),
        realpath( $packageRoot . '/config' ),
        realpath( $packageRoot . '/resources' ),
        realpath( $packageRoot . '/database' ),
    ] );

    $owned = [];

    foreach ( ServiceProvider::$publishGroups as $tag => $paths ) {
        foreach ( array_keys( $paths ) as $from ) {
            $resolved = realpath( $from );

            if ( false === $resolved ) {
                continue;
            }

            foreach ( $ownedRoots as $root ) {
                if ( str_starts_with( $resolved, (string) $root . DIRECTORY_SEPARATOR ) ) {
                    $owned[] = (string) $tag;
                    continue 3;
                }
            }
        }
    }

    return $owned;
}

test( 'cms-framework-config umbrella publish tag is registered by this package', function (): void {
    expect( cmsFrameworkOwnedPublishTags() )->toContain( 'cms-framework-config' );
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

/**
 * Collects every Markdown file that documents installation steps.
 *
 * The drift in #290 was not confined to the README — `Installation-Guide.md`
 * and `Configuration.md` each instructed a `--tag="config"` that was likewise
 * registered nowhere, so scanning the README alone would leave the same
 * failure free to reappear one directory over.
 *
 * @return array<int, string>
 */
function cmsFrameworkDocFiles(): array
{
    $root  = __DIR__ . '/../../';
    $files = [ $root . 'README.md' ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $root . 'docs', FilesystemIterator::SKIP_DOTS ),
    );

    foreach ( $iterator as $file ) {
        if ( 'md' === $file->getExtension() ) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

test( 'every publish tag the docs instruct is actually registered', function (): void {
    $unregistered = [];

    foreach ( cmsFrameworkDocFiles() as $file ) {
        $contents = file_get_contents( $file );

        if ( false === $contents ) {
            continue;
        }

        preg_match_all( '/--tag=["\']?([a-z0-9._-]+)["\']?/i', $contents, $matches );

        foreach ( array_unique( $matches[1] ) as $tag ) {
            if ( ! array_key_exists( $tag, ServiceProvider::$publishGroups ) ) {
                $unregistered[] = basename( $file ) . ' documents --tag=' . $tag;
            }
        }
    }

    expect( $unregistered )->toBe( [] );
} );

test( 'the docs instruct at least one publish tag', function (): void {
    $found = 0;

    foreach ( cmsFrameworkDocFiles() as $file ) {
        $contents = file_get_contents( $file );
        $found += false === $contents ? 0 : preg_match_all( '/--tag=/', $contents );
    }

    expect( $found )->toBeGreaterThan( 0 );
} );
