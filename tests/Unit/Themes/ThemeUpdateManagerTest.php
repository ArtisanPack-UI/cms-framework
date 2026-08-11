<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\MetadataClient;
use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeNotFoundException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeUpdateException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeValidationException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\UpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Themes\Validation\WpThemeJsonValidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach( function (): void {
    $this->themeManager  = app( ThemeManager::class );
    $this->updateManager = new UpdateManager( $this->themeManager );

    $this->themesPath = base_path( 'themes' );
    $this->tmpPath    = storage_path( 'app/theme-update-tmp' );
    $this->testSlugs  = [];

    File::ensureDirectoryExists( $this->themesPath );
    File::ensureDirectoryExists( $this->tmpPath );

    config()->set( 'cms.themes.cacheEnabled', false );
    config()->set( 'cms.updates.cache_enabled', false );
    config()->set( 'cms.themes.backupPath', 'app/theme-update-tmp/backups' );
    Cache::flush();

    // Release metadata is fetched through raw Guzzle in production; bridge it
    // back onto the `Http::` facade so `Http::fake()` intercepts it here.
    MetadataClient::useHttpFacadeBridge();

    foreach ( ['ap.cmsFramework.theme.updating', 'ap.cmsFramework.theme.updated'] as $hook ) {
        removeAllActions( $hook );
    }
} );

afterEach( function (): void {
    MetadataClient::reset();

    foreach ( $this->testSlugs as $slug ) {
        $path = $this->themesPath . '/' . $slug;
        if ( File::exists( $path ) ) {
            File::deleteDirectory( $path );
        }
    }

    $stagingPath = $this->themesPath . '/.updates';
    if ( File::isDirectory( $stagingPath ) ) {
        File::deleteDirectory( $stagingPath );
    }

    if ( File::isDirectory( $this->tmpPath ) ) {
        File::deleteDirectory( $this->tmpPath );
    }

    foreach ( ['ap.cmsFramework.theme.updating', 'ap.cmsFramework.theme.updated'] as $hook ) {
        removeAllActions( $hook );
    }
} );

/**
 * Install a theme directory on disk and track it for cleanup.
 */
function installTestTheme( string $themesPath, string $slug, array $manifest, array &$slugs, array $extraFiles = [] ): string
{
    $slugs[] = $slug;

    $path = $themesPath . '/' . $slug;
    File::ensureDirectoryExists( $path );
    File::put( $path . '/theme.json', json_encode( $manifest ) );

    foreach ( $extraFiles as $relative => $contents ) {
        File::ensureDirectoryExists( dirname( $path . '/' . $relative ) );
        File::put( $path . '/' . $relative, $contents );
    }

    return $path;
}

/**
 * Build an update ZIP whose single top-level directory is $slug.
 */
function buildThemeUpdateZip( string $tmpPath, string $slug, array $manifest, array $extraFiles = [] ): string
{
    $zipPath = $tmpPath . '/' . $slug . '-update-' . uniqid() . '.zip';

    $zip = new ZipArchive;
    if ( true !== $zip->open( $zipPath, ZipArchive::CREATE ) ) {
        throw new RuntimeException( "Failed to create test ZIP at {$zipPath}" );
    }

    $zip->addEmptyDir( $slug );
    $zip->addFromString( $slug . '/theme.json', json_encode( $manifest ) );

    foreach ( $extraFiles as $relative => $contents ) {
        $zip->addFromString( $slug . '/' . $relative, $contents );
    }

    $zip->close();

    return $zipPath;
}

/**
 * Build a minimal GitHub `/releases` payload.
 */
function themeGithubReleasesResponse( string $tag, array $assets = [], ?string $body = null ): array
{
    return [
        [
            'id'           => 1,
            'tag_name'     => $tag,
            'prerelease'   => false,
            'body'         => $body,
            'published_at' => '2026-08-01T00:00:00Z',
            'html_url'     => 'https://github.com/owner/repo/releases/tag/' . $tag,
            'zipball_url'  => 'https://api.github.com/repos/owner/repo/zipball/' . $tag,
            'assets'       => $assets,
        ],
    ];
}

/**
 * Fake the GitHub release feed, the single-release lookup, and the archive
 * download for a theme update.
 */
function fakeThemeRelease( string $slug, string $tag, string $zipPath ): void
{
    $asset = [
        'name'                 => $slug . '.zip',
        'browser_download_url' => "https://github.com/owner/repo/releases/download/{$tag}/{$slug}.zip",
    ];

    Http::fake( [
        'https://api.github.com/repos/owner/repo/releases' => Http::response(
            themeGithubReleasesResponse( $tag, [$asset] ),
        ),
        "https://api.github.com/repos/owner/repo/releases/tags/{$tag}" => Http::response( [
            'tag_name' => $tag,
            'body'     => null,
            'assets'   => [$asset],
        ] ),
        $asset['browser_download_url'] => Http::response( File::get( $zipPath ) ),
    ] );
}

describe( 'Update Source Resolution', function (): void {
    it( 'normalizes an owner/repo shorthand to a github URL', function (): void {
        $manifest = ['update' => ['github' => 'ArtisanPack-UI/artisanpack-ui-theme']];

        expect( $this->updateManager->resolveUpdateSourceUrl( $manifest, 'my-theme' ) )
            ->toBe( 'https://github.com/ArtisanPack-UI/artisanpack-ui-theme' );
    } );

    it( 'passes a full URL through untouched so other sources can claim it', function (): void {
        $manifest = ['update' => ['url' => 'https://gitlab.com/owner/repo']];

        expect( $this->updateManager->resolveUpdateSourceUrl( $manifest, 'my-theme' ) )
            ->toBe( 'https://gitlab.com/owner/repo' );
    } );

    it( 'prefers an explicit url over the github shorthand', function (): void {
        $manifest = ['update' => [
            'github' => 'owner/repo',
            'url'    => 'https://gitlab.com/owner/repo',
        ]];

        expect( $this->updateManager->resolveUpdateSourceUrl( $manifest, 'my-theme' ) )
            ->toBe( 'https://gitlab.com/owner/repo' );
    } );

    it( 'refuses a plaintext http source', function (): void {
        $manifest = ['update' => ['url' => 'http://example.com/updates.json']];

        expect( $this->updateManager->resolveUpdateSourceUrl( $manifest, 'my-theme' ) )->toBeNull();
    } );

    it( 'refuses a plaintext http github source', function (): void {
        $manifest = ['update' => ['github' => 'http://github.com/owner/repo']];

        expect( $this->updateManager->resolveUpdateSourceUrl( $manifest, 'my-theme' ) )->toBeNull();
    } );

    it( 'returns null for a manifest with no update key', function (): void {
        expect( $this->updateManager->resolveUpdateSourceUrl( ['version' => '1.0.0'], 'my-theme' ) )->toBeNull();
    } );
} );

describe( 'Update Checking', function (): void {
    it( 'reports an available update from a GitHub release', function (): void {
        installTestTheme( $this->themesPath, 'check-theme', [
            'slug'    => 'check-theme',
            'name'    => 'Check Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs );

        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases' => Http::response(
                themeGithubReleasesResponse( 'v2.0.0', [
                    [
                        'name'                 => 'check-theme.zip',
                        'browser_download_url' => 'https://github.com/owner/repo/releases/download/v2.0.0/check-theme.zip',
                    ],
                ] ),
            ),
        ] );

        $updateInfo = $this->updateManager->checkThemeUpdate( 'check-theme' );

        expect( $updateInfo )->not->toBeNull()
            ->and( $updateInfo['version'] )->toBe( '2.0.0' )
            ->and( $updateInfo['current'] )->toBe( '1.0.0' )
            ->and( $updateInfo['download_url'] )
            ->toBe( 'https://github.com/owner/repo/releases/download/v2.0.0/check-theme.zip' )
            ->and( $updateInfo['metadata']['source'] )->toBe( 'github' );
    } );

    it( 'surfaces the sha256 published in the release body', function (): void {
        $digest = str_repeat( 'a', 64 );

        installTestTheme( $this->themesPath, 'digest-theme', [
            'slug'    => 'digest-theme',
            'name'    => 'Digest Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs );

        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases' => Http::response(
                themeGithubReleasesResponse(
                    'v2.0.0',
                    [
                        [
                            'name'                 => 'digest-theme.zip',
                            'browser_download_url' => 'https://github.com/owner/repo/releases/download/v2.0.0/digest-theme.zip',
                        ],
                    ],
                    "Release notes\n\nSHA-256: {$digest}",
                ),
            ),
        ] );

        expect( $this->updateManager->checkThemeUpdate( 'digest-theme' )['sha256'] )->toBe( $digest );
    } );

    it( 'returns null when the latest release is not newer', function (): void {
        installTestTheme( $this->themesPath, 'current-theme', [
            'slug'    => 'current-theme',
            'name'    => 'Current Theme',
            'version' => '2.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs );

        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases' => Http::response(
                themeGithubReleasesResponse( 'v2.0.0' ),
            ),
        ] );

        expect( $this->updateManager->checkThemeUpdate( 'current-theme' ) )->toBeNull();
    } );

    it( 'returns null and sends nothing when the theme declares no update source', function (): void {
        installTestTheme( $this->themesPath, 'sourceless-theme', [
            'slug'    => 'sourceless-theme',
            'name'    => 'Sourceless Theme',
            'version' => '1.0.0',
        ], $this->testSlugs );

        Http::fake();

        expect( $this->updateManager->checkThemeUpdate( 'sourceless-theme' ) )->toBeNull();

        Http::assertNothingSent();
    } );

    it( 'does not check at all when the declared source is insecure', function (): void {
        installTestTheme( $this->themesPath, 'insecure-theme', [
            'slug'    => 'insecure-theme',
            'name'    => 'Insecure Theme',
            'version' => '1.0.0',
            'update'  => ['url' => 'http://example.com/updates.json'],
        ], $this->testSlugs );

        Http::fake();

        expect( $this->updateManager->checkThemeUpdate( 'insecure-theme' ) )->toBeNull();

        Http::assertNothingSent();
    } );

    it( 'returns null for a theme that is not installed', function (): void {
        expect( $this->updateManager->checkThemeUpdate( 'no-such-theme' ) )->toBeNull();
    } );

    it( 'throws rather than reporting "current" when the GitHub API fails', function (): void {
        installTestTheme( $this->themesPath, 'failing-theme', [
            'slug'    => 'failing-theme',
            'name'    => 'Failing Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs );

        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases' => Http::response( null, 404 ),
        ] );

        // "The source says nothing is newer" and "we never reached the source"
        // must not collapse into the same answer.
        expect( fn () => $this->updateManager->checkThemeUpdate( 'failing-theme' ) )
            ->toThrow( UpdateException::class );
    } );

    it( 'does not cache a failed check as "no update available"', function (): void {
        installTestTheme( $this->themesPath, 'retry-theme', [
            'slug'    => 'retry-theme',
            'name'    => 'Retry Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs );

        // A sequence, not two `Http::fake()` calls — the second call merges
        // into the existing stubs rather than replacing them, so the original
        // response would keep matching first.
        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases' => Http::sequence()
                ->push( null, 500 )
                ->push( themeGithubReleasesResponse( 'v2.0.0' ), 200 ),
        ] );

        expect( fn () => $this->updateManager->checkThemeUpdate( 'retry-theme' ) )
            ->toThrow( UpdateException::class );

        expect( Cache::has( 'theme.update.retry-theme' ) )->toBeFalse();

        // The next check must actually retry rather than serve a cached
        // "already up to date" for the next twelve hours.
        expect( $this->updateManager->checkThemeUpdate( 'retry-theme' )['version'] )->toBe( '2.0.0' );
    } );

    it( 'caches a negative result so the TTL actually applies', function (): void {
        installTestTheme( $this->themesPath, 'negative-cache-theme', [
            'slug'    => 'negative-cache-theme',
            'name'    => 'Negative Cache Theme',
            'version' => '2.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs );

        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases' => Http::response(
                themeGithubReleasesResponse( 'v2.0.0' ),
            ),
        ] );

        expect( $this->updateManager->checkThemeUpdate( 'negative-cache-theme' ) )->toBeNull()
            ->and( Cache::has( 'theme.update.negative-cache-theme' ) )->toBeTrue();

        // The second check must be served from cache. Asserted by request
        // count rather than by re-faking a failure: `Http::fake()` merges into
        // the existing stubs instead of replacing them, so a second fake would
        // never match and the test would pass without the cache doing anything.
        $sentAfterFirstCheck = Http::recorded()->count();

        expect( $this->updateManager->checkThemeUpdate( 'negative-cache-theme' ) )->toBeNull()
            ->and( Http::recorded()->count() )->toBe( $sentAfterFirstCheck );
    } );

    it( 'skips an unreachable theme rather than failing the whole listing', function (): void {
        installTestTheme( $this->themesPath, 'reachable-theme', [
            'slug'    => 'reachable-theme',
            'name'    => 'Reachable Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs );

        installTestTheme( $this->themesPath, 'unreachable-theme', [
            'slug'    => 'unreachable-theme',
            'name'    => 'Unreachable Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/broken'],
        ], $this->testSlugs );

        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases' => Http::response(
                themeGithubReleasesResponse( 'v2.0.0' ),
            ),
            'https://api.github.com/repos/owner/broken/releases' => Http::response( null, 500 ),
        ] );

        $updates = $this->updateManager->checkForUpdates();

        expect( $updates )->toHaveKey( 'reachable-theme' )
            ->and( $updates )->not->toHaveKey( 'unreachable-theme' );
    } );

    it( 'ignores a malformed update key that never passed install-time validation', function (): void {
        // A theme copied into `themes/` by hand never runs `validateManifest()`,
        // so `resolveUpdateSourceUrl()` cannot assume the key was checked.
        installTestTheme( $this->themesPath, 'handplaced-theme', [
            'slug'    => 'handplaced-theme',
            'name'    => 'Hand Placed Theme',
            'version' => '1.0.0',
            'update'  => ['github' => '../../evil'],
        ], $this->testSlugs );

        Http::fake();

        expect( $this->updateManager->checkThemeUpdate( 'handplaced-theme' ) )->toBeNull();

        Http::assertNothingSent();
    } );

    it( 'collects updates across every installed theme', function (): void {
        installTestTheme( $this->themesPath, 'stale-theme', [
            'slug'    => 'stale-theme',
            'name'    => 'Stale Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs );

        installTestTheme( $this->themesPath, 'fresh-theme', [
            'slug'    => 'fresh-theme',
            'name'    => 'Fresh Theme',
            'version' => '9.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs );

        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases' => Http::response(
                themeGithubReleasesResponse( 'v2.0.0' ),
            ),
        ] );

        $updates = $this->updateManager->checkForUpdates();

        expect( $updates )->toHaveKey( 'stale-theme' )
            ->and( $updates )->not->toHaveKey( 'fresh-theme' )
            ->and( $updates['stale-theme']['version'] )->toBe( '2.0.0' );
    } );
} );

describe( 'Archive Integrity Verification', function (): void {
    beforeEach( function (): void {
        $this->archive = $this->tmpPath . '/archive.zip';
        File::put( $this->archive, 'archive-contents' );
        $this->digest = hash_file( 'sha256', $this->archive );
    } );

    it( 'accepts an archive whose digest matches', function (): void {
        expect( fn () => invokeMethod(
            $this->updateManager,
            'verifyArchiveChecksum',
            [$this->archive, $this->digest, '2.0.0'],
        ) )->not->toThrow( Exception::class );
    } );

    it( 'accepts an uppercase digest', function (): void {
        expect( fn () => invokeMethod(
            $this->updateManager,
            'verifyArchiveChecksum',
            [$this->archive, strtoupper( $this->digest ), '2.0.0'],
        ) )->not->toThrow( Exception::class );
    } );

    it( 'rejects an archive whose digest does not match', function (): void {
        expect( fn () => invokeMethod(
            $this->updateManager,
            'verifyArchiveChecksum',
            [$this->archive, str_repeat( 'b', 64 ), '2.0.0'],
        ) )->toThrow( UpdateException::class, 'Checksum mismatch' );
    } );

    it( 'fails closed when the source advertises no digest', function (): void {
        config()->set( 'cms.updates.allow_unverified_updates', false );

        expect( fn () => invokeMethod(
            $this->updateManager,
            'verifyArchiveChecksum',
            [$this->archive, null, '2.0.0'],
        ) )->toThrow( UpdateException::class, 'did not advertise a SHA-256 checksum' );
    } );

    it( 'proceeds without a digest once unverified updates are opted into', function (): void {
        config()->set( 'cms.updates.allow_unverified_updates', true );

        expect( fn () => invokeMethod(
            $this->updateManager,
            'verifyArchiveChecksum',
            [$this->archive, null, '2.0.0'],
        ) )->not->toThrow( Exception::class );
    } );
} );

describe( 'Staging and Swapping', function (): void {
    it( 'stages an update archive without touching the installed theme', function (): void {
        $themePath = installTestTheme( $this->themesPath, 'stage-theme', [
            'slug'    => 'stage-theme',
            'name'    => 'Stage Theme',
            'version' => '1.0.0',
        ], $this->testSlugs );

        $zipPath = buildThemeUpdateZip( $this->tmpPath, 'stage-theme', [
            'slug'    => 'stage-theme',
            'name'    => 'Stage Theme',
            'version' => '2.0.0',
        ] );

        $staged = $this->themeManager->stageThemeFromZip( $zipPath, 'stage-theme' );

        expect( $staged['manifest']['version'] )->toBe( '2.0.0' )
            ->and( File::isDirectory( $staged['path'] ) )->toBeTrue()
            ->and( $staged['path'] )->not->toBe( $themePath );

        // The installed theme is untouched until the caller swaps.
        $installed = json_decode( File::get( $themePath . '/theme.json' ), true );
        expect( $installed['version'] )->toBe( '1.0.0' );

        File::deleteDirectory( $staged['stagingPath'] );
    } );

    it( 'rejects an archive carrying a different theme and leaves no staging directory', function (): void {
        installTestTheme( $this->themesPath, 'target-theme', [
            'slug'    => 'target-theme',
            'name'    => 'Target Theme',
            'version' => '1.0.0',
        ], $this->testSlugs );

        $zipPath = buildThemeUpdateZip( $this->tmpPath, 'other-theme', [
            'slug'    => 'other-theme',
            'name'    => 'Other Theme',
            'version' => '2.0.0',
        ] );

        expect( fn () => $this->themeManager->stageThemeFromZip( $zipPath, 'target-theme' ) )
            ->toThrow( ThemeValidationException::class );

        $staging = $this->themesPath . '/.updates';
        expect( File::isDirectory( $staging ) ? File::directories( $staging ) : [] )->toBe( [] );
    } );

    it( 'refuses to stage against a slug that escapes the themes directory', function (): void {
        $zipPath = buildThemeUpdateZip( $this->tmpPath, 'traversal-theme', [
            'slug'    => 'traversal-theme',
            'name'    => 'Traversal Theme',
            'version' => '2.0.0',
        ] );

        expect( fn () => $this->themeManager->stageThemeFromZip( $zipPath, '../../evil' ) )
            ->toThrow( ThemeValidationException::class, 'Invalid slug format' );
    } );

    it( 'refuses to swap using a slug that escapes the themes directory', function (): void {
        $stagingPath = $this->themeManager->getStagingPath() . '/legit-' . uniqid();
        File::ensureDirectoryExists( $stagingPath . '/legit-theme' );
        File::put( $stagingPath . '/legit-theme/theme.json', '{}' );

        expect( fn () => $this->themeManager->swapStagedTheme( '../../evil', $stagingPath . '/legit-theme' ) )
            ->toThrow( ThemeUpdateException::class );
    } );

    it( 'refuses to swap in a directory from outside the staging root', function (): void {
        installTestTheme( $this->themesPath, 'guarded-theme', [
            'slug'    => 'guarded-theme',
            'name'    => 'Guarded Theme',
            'version' => '1.0.0',
        ], $this->testSlugs );

        $outsidePath = $this->tmpPath . '/not-staged';
        File::ensureDirectoryExists( $outsidePath );

        expect( fn () => $this->themeManager->swapStagedTheme( 'guarded-theme', $outsidePath ) )
            ->toThrow( ThemeUpdateException::class );

        // The installed theme must survive a rejected swap.
        expect( File::exists( $this->themesPath . '/guarded-theme/theme.json' ) )->toBeTrue();
    } );

    it( 'swaps a staged directory into place', function (): void {
        $themePath = installTestTheme( $this->themesPath, 'swap-theme', [
            'slug'    => 'swap-theme',
            'name'    => 'Swap Theme',
            'version' => '1.0.0',
        ], $this->testSlugs, ['index.blade.php' => 'old'] );

        $zipPath = buildThemeUpdateZip( $this->tmpPath, 'swap-theme', [
            'slug'    => 'swap-theme',
            'name'    => 'Swap Theme',
            'version' => '2.0.0',
        ], ['index.blade.php' => 'new'] );

        $staged = $this->themeManager->stageThemeFromZip( $zipPath, 'swap-theme' );

        $this->themeManager->swapStagedTheme( 'swap-theme', $staged['path'] );

        expect( File::get( $themePath . '/index.blade.php' ) )->toBe( 'new' )
            ->and( json_decode( File::get( $themePath . '/theme.json' ), true )['version'] )->toBe( '2.0.0' );
    } );
} );

describe( 'Theme Updating', function (): void {
    beforeEach( function (): void {
        config()->set( 'cms.updates.allow_unverified_updates', true );
    } );

    it( 'installs an update and fires the lifecycle hooks', function (): void {
        $themePath = installTestTheme( $this->themesPath, 'live-theme', [
            'slug'    => 'live-theme',
            'name'    => 'Live Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs, ['index.blade.php' => 'old markup'] );

        $zipPath = buildThemeUpdateZip( $this->tmpPath, 'live-theme', [
            'slug'    => 'live-theme',
            'name'    => 'Live Theme',
            'version' => '2.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], ['index.blade.php' => 'new markup'] );

        fakeThemeRelease( 'live-theme', 'v2.0.0', $zipPath );

        $fired = [];
        addAction( 'ap.cmsFramework.theme.updating', function ( $slug, $old, $new ) use ( &$fired ): void {
            $fired['updating'] = [$slug, $old, $new];
        } );
        addAction( 'ap.cmsFramework.theme.updated', function ( $slug, $version ) use ( &$fired ): void {
            $fired['updated'] = [$slug, $version];
        } );

        expect( $this->updateManager->updateTheme( 'live-theme' ) )->toBeTrue()
            ->and( File::get( $themePath . '/index.blade.php' ) )->toBe( 'new markup' )
            ->and( $this->themeManager->getTheme( 'live-theme' )['version'] )->toBe( '2.0.0' )
            ->and( $fired['updating'] )->toBe( ['live-theme', '1.0.0', '2.0.0'] )
            ->and( $fired['updated'] )->toBe( ['live-theme', '2.0.0'] );
    } );

    it( 'leaves no staging directory behind after a successful update', function (): void {
        installTestTheme( $this->themesPath, 'tidy-theme', [
            'slug'    => 'tidy-theme',
            'name'    => 'Tidy Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs );

        $zipPath = buildThemeUpdateZip( $this->tmpPath, 'tidy-theme', [
            'slug'    => 'tidy-theme',
            'name'    => 'Tidy Theme',
            'version' => '2.0.0',
        ] );

        fakeThemeRelease( 'tidy-theme', 'v2.0.0', $zipPath );

        $this->updateManager->updateTheme( 'tidy-theme' );

        $staging = $this->themesPath . '/.updates';
        expect( File::isDirectory( $staging ) ? File::directories( $staging ) : [] )->toBe( [] );
    } );

    it( 'does not revert a completed update when an updated listener throws', function (): void {
        $themePath = installTestTheme( $this->themesPath, 'noisy-listener-theme', [
            'slug'    => 'noisy-listener-theme',
            'name'    => 'Noisy Listener Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs, ['index.blade.php' => 'old'] );

        $zipPath = buildThemeUpdateZip( $this->tmpPath, 'noisy-listener-theme', [
            'slug'    => 'noisy-listener-theme',
            'name'    => 'Noisy Listener Theme',
            'version' => '2.0.0',
        ], ['index.blade.php' => 'new'] );

        fakeThemeRelease( 'noisy-listener-theme', 'v2.0.0', $zipPath );

        addAction( 'ap.cmsFramework.theme.updated', function (): void {
            throw new RuntimeException( 'Listener exploded.' );
        } );

        expect( fn () => $this->updateManager->updateTheme( 'noisy-listener-theme' ) )
            ->toThrow( RuntimeException::class, 'Listener exploded.' );

        // `theme.updated` is a notification, not a veto. The swapped-in files
        // are already validated and must survive a badly-behaved listener.
        expect( File::get( $themePath . '/index.blade.php' ) )->toBe( 'new' )
            ->and( $this->themeManager->getTheme( 'noisy-listener-theme' )['version'] )->toBe( '2.0.0' );
    } );

    it( 'returns false when the theme is already current', function (): void {
        installTestTheme( $this->themesPath, 'uptodate-theme', [
            'slug'    => 'uptodate-theme',
            'name'    => 'Up To Date Theme',
            'version' => '2.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs );

        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases' => Http::response(
                themeGithubReleasesResponse( 'v2.0.0' ),
            ),
        ] );

        expect( $this->updateManager->updateTheme( 'uptodate-theme' ) )->toBeFalse();
    } );

    it( 'throws when the theme is not installed', function (): void {
        expect( fn () => $this->updateManager->updateTheme( 'ghost-theme' ) )
            ->toThrow( ThemeNotFoundException::class );
    } );

    it( 'aborts without touching the installed theme when a listener vetoes', function (): void {
        $themePath = installTestTheme( $this->themesPath, 'vetoed-theme', [
            'slug'    => 'vetoed-theme',
            'name'    => 'Vetoed Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs, ['index.blade.php' => 'untouched'] );

        $zipPath = buildThemeUpdateZip( $this->tmpPath, 'vetoed-theme', [
            'slug'    => 'vetoed-theme',
            'name'    => 'Vetoed Theme',
            'version' => '2.0.0',
        ], ['index.blade.php' => 'replaced'] );

        fakeThemeRelease( 'vetoed-theme', 'v2.0.0', $zipPath );

        addAction( 'ap.cmsFramework.theme.updating', function (): void {
            throw new RuntimeException( 'Not on my watch.' );
        } );

        expect( fn () => $this->updateManager->updateTheme( 'vetoed-theme' ) )
            ->toThrow( RuntimeException::class, 'Not on my watch.' );

        expect( File::get( $themePath . '/index.blade.php' ) )->toBe( 'untouched' )
            ->and( $this->themeManager->getTheme( 'vetoed-theme' )['version'] )->toBe( '1.0.0' );
    } );

    it( 'refuses an archive whose digest does not match and leaves the theme intact', function (): void {
        $themePath = installTestTheme( $this->themesPath, 'corrupt-theme', [
            'slug'    => 'corrupt-theme',
            'name'    => 'Corrupt Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs, ['index.blade.php' => 'original'] );

        $zipPath = buildThemeUpdateZip( $this->tmpPath, 'corrupt-theme', [
            'slug'    => 'corrupt-theme',
            'name'    => 'Corrupt Theme',
            'version' => '2.0.0',
        ], ['index.blade.php' => 'tampered'] );

        $asset = [
            'name'                 => 'corrupt-theme.zip',
            'browser_download_url' => 'https://github.com/owner/repo/releases/download/v2.0.0/corrupt-theme.zip',
        ];

        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases' => Http::response(
                themeGithubReleasesResponse( 'v2.0.0', [$asset], 'SHA-256: ' . str_repeat( 'b', 64 ) ),
            ),
            'https://api.github.com/repos/owner/repo/releases/tags/v2.0.0' => Http::response( [
                'tag_name' => 'v2.0.0',
                'body'     => 'SHA-256: ' . str_repeat( 'b', 64 ),
                'assets'   => [$asset],
            ] ),
            $asset['browser_download_url'] => Http::response( File::get( $zipPath ) ),
        ] );

        expect( fn () => $this->updateManager->updateTheme( 'corrupt-theme' ) )
            ->toThrow( ThemeUpdateException::class, 'Checksum mismatch' );

        expect( File::get( $themePath . '/index.blade.php' ) )->toBe( 'original' )
            ->and( $this->themeManager->getTheme( 'corrupt-theme' )['version'] )->toBe( '1.0.0' );
    } );

    it( 'refuses an archive containing a different theme and leaves the theme intact', function (): void {
        $themePath = installTestTheme( $this->themesPath, 'mismatch-theme', [
            'slug'    => 'mismatch-theme',
            'name'    => 'Mismatch Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs, ['index.blade.php' => 'original'] );

        $zipPath = buildThemeUpdateZip( $this->tmpPath, 'impostor-theme', [
            'slug'    => 'impostor-theme',
            'name'    => 'Impostor Theme',
            'version' => '2.0.0',
        ] );

        fakeThemeRelease( 'mismatch-theme', 'v2.0.0', $zipPath );

        expect( fn () => $this->updateManager->updateTheme( 'mismatch-theme' ) )
            ->toThrow( ThemeUpdateException::class );

        expect( File::get( $themePath . '/index.blade.php' ) )->toBe( 'original' )
            ->and( $this->themeManager->getTheme( 'mismatch-theme' )['version'] )->toBe( '1.0.0' );
    } );

    it( 'restores from backup when the swap fails after disturbing the live directory', function (): void {
        $slug      = 'unrecoverable-swap-theme';
        $themePath = installTestTheme( $this->themesPath, $slug, [
            'slug'    => $slug,
            'name'    => 'Unrecoverable Swap Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs, ['index.blade.php' => 'original'] );

        $zipPath = buildThemeUpdateZip( $this->tmpPath, $slug, [
            'slug'    => $slug,
            'name'    => 'Unrecoverable Swap Theme',
            'version' => '2.0.0',
        ], ['index.blade.php' => 'replacement'] );

        fakeThemeRelease( $slug, 'v2.0.0', $zipPath );

        // Stands in for the one case `swapStagedTheme()` cannot recover from on
        // its own: the live directory has been moved aside and the undo rename
        // also failed, so only the backup can put the theme back.
        $themeManager = new class( app( SettingsManager::class ), app( WpThemeJsonValidator::class ) ) extends ThemeManager {
            public function swapStagedTheme( string $slug, string $stagedThemePath ): void
            {
                File::deleteDirectory( $this->getThemesPath() . '/' . $slug );

                throw new RuntimeException( 'Swap died halfway.' );
            }
        };

        $updateManager = new UpdateManager( $themeManager );

        expect( fn () => $updateManager->updateTheme( $slug ) )
            ->toThrow( ThemeUpdateException::class, 'Swap died halfway.' );

        // The backup is the only thing that can put the theme back here.
        expect( File::exists( $themePath . '/theme.json' ) )->toBeTrue()
            ->and( File::get( $themePath . '/index.blade.php' ) )->toBe( 'original' )
            ->and( json_decode( File::get( $themePath . '/theme.json' ), true )['version'] )->toBe( '1.0.0' );
    } );

    it( 'restores from backup and deletes files the update added when the swap is undone', function (): void {
        $slug      = 'rollback-theme';
        $themePath = installTestTheme( $this->themesPath, $slug, [
            'slug'    => $slug,
            'name'    => 'Rollback Theme',
            'version' => '1.0.0',
        ], $this->testSlugs, ['index.blade.php' => 'original'] );

        $backupPath = invokeMethod( $this->updateManager, 'backupTheme', [$slug, '1.0.0'] );

        // Stand in for a swapped-in update: new file added, existing file
        // rewritten. A rollback that only restored backed-up files would leave
        // `added.blade.php` orphaned (#272).
        File::put( $themePath . '/index.blade.php', 'replaced' );
        File::put( $themePath . '/added.blade.php', 'from the failed update' );

        invokeMethod( $this->updateManager, 'restoreFromBackup', [$slug, $backupPath] );

        expect( File::get( $themePath . '/index.blade.php' ) )->toBe( 'original' )
            ->and( File::exists( $themePath . '/added.blade.php' ) )->toBeFalse();
    } );

    it( 'leaves the failed update in place when the backup archive is unreadable', function (): void {
        $slug      = 'unreadable-backup-theme';
        $themePath = installTestTheme( $this->themesPath, $slug, [
            'slug'    => $slug,
            'name'    => 'Unreadable Backup Theme',
            'version' => '1.0.0',
        ], $this->testSlugs, ['index.blade.php' => 'present' ] );

        $backupPath = $this->tmpPath . '/not-a-zip.zip';
        File::put( $backupPath, 'definitely not a zip archive' );

        invokeMethod( $this->updateManager, 'restoreFromBackup', [$slug, $backupPath] );

        // Deleting the directory with nothing to put back would be strictly
        // worse than leaving the failed update on disk.
        expect( File::exists( $themePath . '/index.blade.php' ) )->toBeTrue();
    } );

    it( 'forgets the cached update check after a successful update', function (): void {
        installTestTheme( $this->themesPath, 'cache-theme', [
            'slug'    => 'cache-theme',
            'name'    => 'Cache Theme',
            'version' => '1.0.0',
            'update'  => ['github' => 'owner/repo'],
        ], $this->testSlugs );

        $zipPath = buildThemeUpdateZip( $this->tmpPath, 'cache-theme', [
            'slug'    => 'cache-theme',
            'name'    => 'Cache Theme',
            'version' => '2.0.0',
            'update'  => ['github' => 'owner/repo'],
        ] );

        fakeThemeRelease( 'cache-theme', 'v2.0.0', $zipPath );

        $this->updateManager->checkThemeUpdate( 'cache-theme' );
        expect( Cache::has( 'theme.update.cache-theme' ) )->toBeTrue();

        $this->updateManager->updateTheme( 'cache-theme' );

        expect( Cache::has( 'theme.update.cache-theme' ) )->toBeFalse();
    } );
} );
