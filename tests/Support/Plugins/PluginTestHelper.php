<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Support\Plugins;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

/**
 * Mock ZIP and update-feed infrastructure for the plugin update tests (#42).
 *
 * Builds plugin archives laid out the way `UpdateManager` extracts them — a
 * single top-level directory named after the slug — and fakes the legacy
 * `update_url` feed plus its download, so the whole update flow runs without
 * the network.
 */
class PluginTestHelper
{
    /**
     * The URL the faked update feed answers on for a slug.
     *
     * @param  string  $slug  Plugin slug.
     *
     * @return string The feed URL.
     */
    public static function updateFeedUrl( string $slug ): string
    {
        return 'https://example.com/updates/' . $slug;
    }

    /**
     * The URL the faked update archive downloads from.
     *
     * @param  string  $slug  Plugin slug.
     * @param  string  $version  Version the archive carries.
     *
     * @return string The download URL.
     */
    public static function downloadUrl( string $slug, string $version ): string
    {
        return 'https://example.com/downloads/' . $slug . '-' . $version . '.zip';
    }

    /**
     * The path of a committed fixture archive in `tests/Fixtures/Plugins`.
     *
     * The fixtures share the layout {@see self::buildPluginZip()} produces:
     * `test-plugin` 1.0.0 ships `src/Legacy.php`, and 2.0.0 drops it in favour
     * of `src/Feature.php`, so a test can tell the old files were replaced.
     *
     * @param  string  $name  Fixture name without extension, e.g. `test-plugin-2.0.0`.
     *
     * @throws RuntimeException When the fixture does not exist.
     *
     * @return string Absolute path to the fixture ZIP.
     */
    public static function fixturePath( string $name ): string
    {
        $path = dirname( __DIR__, 2 ) . '/Fixtures/Plugins/' . $name . '.zip';

        if ( ! File::exists( $path ) ) {
            throw new RuntimeException( "Plugin fixture [{$name}] does not exist." );
        }

        return $path;
    }

    /**
     * Build a plugin ZIP whose top-level directory is the manifest's slug.
     *
     * @param  array<string,mixed>  $manifest  The `plugin.json` contents; must carry a `slug`.
     * @param  array<string,string>  $files  Extra files keyed by path relative to the plugin root.
     * @param  string|null  $zipPath  Where to write the archive; a temp file under `storage/app` when null.
     *
     * @throws RuntimeException When the archive cannot be written.
     *
     * @return string Path to the written ZIP.
     */
    public static function buildPluginZip( array $manifest, array $files = [], ?string $zipPath = null ): string
    {
        $zipPath ??= storage_path( 'app/test-update-' . uniqid() . '.zip' );

        File::ensureDirectoryExists( dirname( $zipPath ) );

        $zip = new ZipArchive;

        if ( true !== $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            throw new RuntimeException( "Could not create plugin archive [{$zipPath}]." );
        }

        $zip->addFromString( $manifest['slug'] . '/plugin.json', json_encode( $manifest, JSON_THROW_ON_ERROR ) );

        foreach ( $files as $relativePath => $contents ) {
            $zip->addFromString( $manifest['slug'] . '/' . $relativePath, $contents );
        }

        if ( ! $zip->close() ) {
            throw new RuntimeException( "Could not write plugin archive [{$zipPath}]." );
        }

        return $zipPath;
    }

    /**
     * Build an update ZIP for `valid-plugin` at 2.0.0, the shape most update
     * tests need, with $manifestOverrides merged over a clean manifest.
     *
     * @param  array<string,mixed>  $manifestOverrides  Manifest keys to add or replace.
     *
     * @return string Path to the written ZIP.
     */
    public static function buildUpdateZip( array $manifestOverrides = [] ): string
    {
        return self::buildPluginZip(
            array_merge( [
                'slug'        => 'valid-plugin',
                'name'        => 'Valid Test Plugin',
                'version'     => '2.0.0',
                'description' => 'Updated plugin',
                'author'      => 'Test Author',
            ], $manifestOverrides ),
            [ 'src/Stub.php' => "<?php\n" ],
        );
    }

    /**
     * Extract a plugin archive into a plugins directory, as an install would.
     *
     * @param  string  $zipPath  The archive to extract.
     * @param  string  $pluginsPath  The plugins root to extract into.
     *
     * @throws RuntimeException When the archive cannot be opened.
     */
    public static function extractPluginZip( string $zipPath, string $pluginsPath ): void
    {
        $zip = new ZipArchive;

        if ( true !== $zip->open( $zipPath ) ) {
            throw new RuntimeException( "Could not open plugin archive [{$zipPath}]." );
        }

        File::ensureDirectoryExists( $pluginsPath );
        $zip->extractTo( $pluginsPath );
        $zip->close();
    }

    /**
     * Fake the legacy update feed for a slug and the archive it points at.
     *
     * The feed advertises $version, the download URL, and the archive's
     * sha256 (pass $sha256 to advertise a different digest). Any other
     * request fails rather than reaching the network. `Http::fake()` stubs
     * stack and the first match wins, so call this once per test.
     *
     * @param  string  $slug  Plugin slug.
     * @param  string  $version  Version the feed advertises.
     * @param  string  $zipPath  Archive served at the download URL.
     * @param  string|null  $sha256  Digest to advertise; the archive's own when null.
     */
    public static function fakeUpdateFeed( string $slug, string $version, string $zipPath, ?string $sha256 = null ): void
    {
        $contents = File::get( $zipPath );

        Http::preventStrayRequests();
        Http::fake( [
            self::updateFeedUrl( $slug ) => Http::response( [
                'version'      => $version,
                'download_url' => self::downloadUrl( $slug, $version ),
                'sha256'       => $sha256 ?? hash( 'sha256', $contents ),
            ] ),
            self::downloadUrl( $slug, $version ) => Http::response( $contents ),
        ] );
    }
}
