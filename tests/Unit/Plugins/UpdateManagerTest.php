<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\MetadataClient;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginUpdateException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\UpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Illuminate\Support\Facades\Http;

beforeEach( function (): void {
    $this->pluginManager = app( PluginManager::class );
    $this->updateManager = new UpdateManager( $this->pluginManager );

    // Release metadata is fetched through raw Guzzle in production; bridge it
    // back onto the `Http::` facade so `Http::fake()` intercepts it here.
    MetadataClient::useHttpFacadeBridge();
} );

afterEach( function (): void {
    MetadataClient::reset();
} );

/**
 * Build a minimal GitHub `/releases` payload.
 */
function githubReleasesResponse( string $tag, array $assets = [], ?string $body = null ): array
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

describe( 'Update Checking', function (): void {
    it( 'checks for updates for all plugins', function (): void {
        // Create plugins with update URLs
        Plugin::create( [
            'slug'    => 'test-plugin-1',
            'name'    => 'Test Plugin 1',
            'version' => '1.0.0',
            'meta'    => [
                'update_url' => 'https://example.com/updates/test-plugin-1',
            ],
        ] );

        Plugin::create( [
            'slug'    => 'test-plugin-2',
            'name'    => 'Test Plugin 2',
            'version' => '1.0.0',
            'meta'    => [
                'update_url' => 'https://example.com/updates/test-plugin-2',
            ],
        ] );

        // Mock HTTP responses
        Http::fake( [
            'https://example.com/updates/test-plugin-1' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/downloads/test-plugin-1-2.0.0.zip',
            ] ),
            'https://example.com/updates/test-plugin-2' => Http::response( [
                'version' => '1.0.0', // Same version, no update
            ] ),
        ] );

        $updates = $this->updateManager->checkForUpdates();

        expect( $updates )->toHaveKey( 'test-plugin-1' )
            ->and( $updates['test-plugin-1']['version'] )->toBe( '2.0.0' )
            ->and( $updates )->not->toHaveKey( 'test-plugin-2' );
    } );

    it( 'returns empty array when no updates available', function (): void {
        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '2.0.0',
            'meta'    => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ] );

        Http::fake( [
            'https://example.com/updates/test-plugin' => Http::response( [
                'version' => '1.0.0', // Older version
            ] ),
        ] );

        $updates = $this->updateManager->checkForUpdates();

        expect( $updates )->toBeEmpty();
    } );

    it( 'caches update check results', function (): void {
        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ] );

        Http::fake( [
            'https://example.com/updates/test-plugin' => Http::response( [
                'version' => '2.0.0',
            ] ),
        ] );

        // First call performs the check and caches it; the second is served
        // from cache without a second HTTP round-trip.
        $first  = $this->updateManager->checkPluginUpdate( 'test-plugin' );
        $second = $this->updateManager->checkPluginUpdate( 'test-plugin' );

        expect( $first )->toBeArray()
            ->and( $second )->toBe( $first );

        Http::assertSentCount( 1 );
    } );
} );

describe( 'Update Detection', function (): void {
    it( 'detects available updates using semantic versioning', function (): void {
        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ] );

        Http::fake( [
            'https://example.com/updates/test-plugin' => Http::response( [
                'version' => '1.0.1',
            ] ),
        ] );

        $updateInfo = $this->updateManager->checkPluginUpdate( 'test-plugin' );

        expect( $updateInfo )->not->toBeNull()
            ->and( $updateInfo['version'] )->toBe( '1.0.1' );
    } );

    it( 'does not detect update when version is same', function (): void {
        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ] );

        Http::fake( [
            'https://example.com/updates/test-plugin' => Http::response( [
                'version' => '1.0.0',
            ] ),
        ] );

        $updateInfo = $this->updateManager->checkPluginUpdate( 'test-plugin' );

        expect( $updateInfo )->toBeNull();
    } );

    it( 'does not detect update when version is older', function (): void {
        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '2.0.0',
            'meta'    => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ] );

        Http::fake( [
            'https://example.com/updates/test-plugin' => Http::response( [
                'version' => '1.0.0',
            ] ),
        ] );

        $updateInfo = $this->updateManager->checkPluginUpdate( 'test-plugin' );

        expect( $updateInfo )->toBeNull();
    } );
} );

describe( 'Update URL Handling', function (): void {
    it( 'returns null when plugin has no update URL', function (): void {
        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => [], // No update_url
        ] );

        $updateInfo = $this->updateManager->checkPluginUpdate( 'test-plugin' );

        expect( $updateInfo )->toBeNull();
    } );

    it( 'returns null when plugin does not exist', function (): void {
        $updateInfo = $this->updateManager->checkPluginUpdate( 'non-existent-plugin' );

        expect( $updateInfo )->toBeNull();
    } );

    it( 'handles failed HTTP requests gracefully', function (): void {
        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ] );

        Http::fake( [
            'https://example.com/updates/test-plugin' => Http::response( null, 500 ),
        ] );

        $updateInfo = $this->updateManager->checkPluginUpdate( 'test-plugin' );

        expect( $updateInfo )->toBeNull();
    } );

    it( 'handles network timeouts gracefully', function (): void {
        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ] );

        Http::fake( [
            'https://example.com/updates/test-plugin' => function (): void {
                throw new Exception( 'Connection timeout' );
            },
        ] );

        $updateInfo = $this->updateManager->checkPluginUpdate( 'test-plugin' );

        expect( $updateInfo )->toBeNull();
    } );
} );

describe( 'Version Comparison', function (): void {
    it( 'correctly compares major version changes', function (): void {
        $updateManager = new UpdateManager( $this->pluginManager );
        $reflection    = new ReflectionClass( $updateManager );
        $method        = $reflection->getMethod( 'isUpdateAvailable' );
        $method->setAccessible( true );

        expect( $method->invoke( $updateManager, '1.0.0', '2.0.0' ) )->toBeTrue()
            ->and( $method->invoke( $updateManager, '2.0.0', '1.0.0' ) )->toBeFalse();
    } );

    it( 'correctly compares minor version changes', function (): void {
        $updateManager = new UpdateManager( $this->pluginManager );
        $reflection    = new ReflectionClass( $updateManager );
        $method        = $reflection->getMethod( 'isUpdateAvailable' );
        $method->setAccessible( true );

        expect( $method->invoke( $updateManager, '1.0.0', '1.1.0' ) )->toBeTrue()
            ->and( $method->invoke( $updateManager, '1.1.0', '1.0.0' ) )->toBeFalse();
    } );

    it( 'correctly compares patch version changes', function (): void {
        $updateManager = new UpdateManager( $this->pluginManager );
        $reflection    = new ReflectionClass( $updateManager );
        $method        = $reflection->getMethod( 'isUpdateAvailable' );
        $method->setAccessible( true );

        expect( $method->invoke( $updateManager, '1.0.0', '1.0.1' ) )->toBeTrue()
            ->and( $method->invoke( $updateManager, '1.0.1', '1.0.0' ) )->toBeFalse();
    } );

    it( 'returns false for identical versions', function (): void {
        $updateManager = new UpdateManager( $this->pluginManager );
        $reflection    = new ReflectionClass( $updateManager );
        $method        = $reflection->getMethod( 'isUpdateAvailable' );
        $method->setAccessible( true );

        expect( $method->invoke( $updateManager, '1.0.0', '1.0.0' ) )->toBeFalse();
    } );
} );

describe( 'Update Source Resolution', function (): void {
    it( 'normalizes an owner/repo shorthand to a github URL', function (): void {
        $plugin = new Plugin( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => ['update' => ['github' => 'ArtisanPack-UI/artisanpack-ui-plugin']],
        ] );

        expect( $this->updateManager->resolveUpdateSourceUrl( $plugin ) )
            ->toBe( 'https://github.com/ArtisanPack-UI/artisanpack-ui-plugin' );
    } );

    it( 'passes a full URL through untouched so other sources can claim it', function (): void {
        $plugin = new Plugin( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => ['update' => ['url' => 'https://gitlab.com/owner/repo']],
        ] );

        expect( $this->updateManager->resolveUpdateSourceUrl( $plugin ) )
            ->toBe( 'https://gitlab.com/owner/repo' );
    } );

    it( 'prefers an explicit url over the github shorthand', function (): void {
        $plugin = new Plugin( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => ['update' => [
                'github' => 'owner/repo',
                'url'    => 'https://gitlab.com/owner/repo',
            ]],
        ] );

        expect( $this->updateManager->resolveUpdateSourceUrl( $plugin ) )
            ->toBe( 'https://gitlab.com/owner/repo' );
    } );

    it( 'refuses a plaintext http source', function (): void {
        $plugin = new Plugin( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => ['update' => ['url' => 'http://example.com/updates.json']],
        ] );

        expect( $this->updateManager->resolveUpdateSourceUrl( $plugin ) )->toBeNull();
    } );

    it( 'refuses a plaintext http github source', function (): void {
        $plugin = new Plugin( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => ['update' => ['github' => 'http://github.com/owner/repo']],
        ] );

        expect( $this->updateManager->resolveUpdateSourceUrl( $plugin ) )->toBeNull();
    } );

    it( 'does not check for updates at all when the declared source is insecure', function (): void {
        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => ['update' => ['url' => 'http://example.com/updates.json']],
        ] );

        Http::fake();

        expect( $this->updateManager->checkPluginUpdate( 'test-plugin' ) )->toBeNull();

        Http::assertNothingSent();
    } );

    it( 'returns null for a manifest with no update key', function (): void {
        $plugin = new Plugin( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => ['update_url' => 'https://example.com/updates/test-plugin'],
        ] );

        expect( $this->updateManager->resolveUpdateSourceUrl( $plugin ) )->toBeNull();
    } );
} );

describe( 'GitHub Releases Update Source', function (): void {
    it( 'reports an available update from a GitHub release', function (): void {
        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => ['update' => ['github' => 'owner/repo']],
        ] );

        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases' => Http::response(
                githubReleasesResponse( 'v2.0.0', [
                    [
                        'name'                 => 'test-plugin.zip',
                        'browser_download_url' => 'https://github.com/owner/repo/releases/download/v2.0.0/test-plugin.zip',
                    ],
                ] ),
            ),
        ] );

        $updateInfo = $this->updateManager->checkPluginUpdate( 'test-plugin' );

        expect( $updateInfo )->not->toBeNull()
            ->and( $updateInfo['version'] )->toBe( '2.0.0' )
            ->and( $updateInfo['download_url'] )
            ->toBe( 'https://github.com/owner/repo/releases/download/v2.0.0/test-plugin.zip' )
            ->and( $updateInfo['current'] )->toBe( '1.0.0' )
            ->and( $updateInfo['metadata']['source'] )->toBe( 'github' );
    } );

    it( 'surfaces the sha256 published in the release body', function (): void {
        $digest = str_repeat( 'a', 64 );

        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => ['update' => ['github' => 'owner/repo']],
        ] );

        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases' => Http::response(
                githubReleasesResponse(
                    'v2.0.0',
                    [
                        [
                            'name'                 => 'test-plugin.zip',
                            'browser_download_url' => 'https://github.com/owner/repo/releases/download/v2.0.0/test-plugin.zip',
                        ],
                    ],
                    "Release notes\n\nSHA-256: {$digest}",
                ),
            ),
        ] );

        $updateInfo = $this->updateManager->checkPluginUpdate( 'test-plugin' );

        expect( $updateInfo['sha256'] )->toBe( $digest );
    } );

    it( 'returns null when the latest release is not newer', function (): void {
        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '2.0.0',
            'meta'    => ['update' => ['github' => 'owner/repo']],
        ] );

        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases' => Http::response(
                githubReleasesResponse( 'v2.0.0' ),
            ),
        ] );

        expect( $this->updateManager->checkPluginUpdate( 'test-plugin' ) )->toBeNull();
    } );

    it( 'prefers the update key over a legacy update_url', function (): void {
        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => [
                'update'     => ['github' => 'owner/repo'],
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ] );

        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases' => Http::response(
                githubReleasesResponse( 'v2.0.0' ),
            ),
            'https://example.com/updates/test-plugin' => Http::response( ['version' => '9.9.9'] ),
        ] );

        $updateInfo = $this->updateManager->checkPluginUpdate( 'test-plugin' );

        expect( $updateInfo['version'] )->toBe( '2.0.0' );

        Http::assertNotSent( fn ( $request ) => 'https://example.com/updates/test-plugin' === $request->url() );
    } );

    it( 'logs and returns null when the GitHub API fails', function (): void {
        Plugin::create( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => ['update' => ['github' => 'owner/repo']],
        ] );

        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases' => Http::response( null, 404 ),
        ] );

        expect( $this->updateManager->checkPluginUpdate( 'test-plugin' ) )->toBeNull();
    } );
} );

describe( 'Archive Integrity Verification', function (): void {
    beforeEach( function (): void {
        $this->archive = tempnam( sys_get_temp_dir(), 'plugin-update-' );
        file_put_contents( $this->archive, 'archive-contents' );
        $this->digest = hash_file( 'sha256', $this->archive );
    } );

    afterEach( function (): void {
        if ( is_string( $this->archive ) && file_exists( $this->archive ) ) {
            unlink( $this->archive );
        }
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

    it( 'skips verification entirely when it is disabled', function (): void {
        config()->set( 'cms.updates.verify_checksum', false );

        expect( fn () => invokeMethod(
            $this->updateManager,
            'verifyArchiveChecksum',
            [$this->archive, str_repeat( 'b', 64 ), '2.0.0'],
        ) )->not->toThrow( Exception::class );
    } );
} );

describe( 'Update Archive Download', function (): void {
    it( 'uses the legacy buffered download for update_url plugins', function (): void {
        $plugin = new Plugin( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => ['update_url' => 'https://example.com/updates/test-plugin'],
        ] );

        Http::fake( [
            'https://example.com/downloads/test-plugin.zip' => Http::response( 'fake-zip-content' ),
        ] );

        $path = invokeMethod( $this->updateManager, 'downloadUpdateArchive', [
            $plugin,
            [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/downloads/test-plugin.zip',
                // The legacy path now enforces the same checksum gate as the
                // source-backed one; a matching digest lets it through.
                'sha256'       => hash( 'sha256', 'fake-zip-content' ),
            ],
        ] );

        expect( file_get_contents( $path ) )->toBe( 'fake-zip-content' );

        unlink( $path );
    } );

    it( 'refuses a legacy update_url download over http', function (): void {
        $plugin = new Plugin( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => ['update_url' => 'https://example.com/updates/test-plugin'],
        ] );

        expect( fn () => invokeMethod( $this->updateManager, 'downloadUpdateArchive', [
            $plugin,
            [
                'version'      => '2.0.0',
                'download_url' => 'http://example.com/downloads/test-plugin.zip',
                'sha256'       => hash( 'sha256', 'fake-zip-content' ),
            ],
        ] ) )->toThrow( PluginUpdateException::class );
    } );

    it( 'refuses a legacy update_url download with no digest when unverified updates are disallowed', function (): void {
        config()->set( 'cms.updates.allow_unverified_updates', false );

        $plugin = new Plugin( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => ['update_url' => 'https://example.com/updates/test-plugin'],
        ] );

        Http::fake( [
            'https://example.com/downloads/test-plugin.zip' => Http::response( 'fake-zip-content' ),
        ] );

        expect( fn () => invokeMethod( $this->updateManager, 'downloadUpdateArchive', [
            $plugin,
            [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/downloads/test-plugin.zip',
            ],
        ] ) )->toThrow( UpdateException::class );
    } );

    it( 'deletes the streamed archive when verification rejects it', function (): void {
        config()->set( 'cms.updates.allow_unverified_updates', false );

        $plugin = new Plugin( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => ['update' => ['github' => 'owner/repo']],
        ] );

        Http::fake( [
            'https://api.github.com/repos/owner/repo/releases/tags/v2.0.0' => Http::response( [
                'tag_name' => 'v2.0.0',
                'body'     => null,
                'assets'   => [
                    [
                        'name'                 => 'test-plugin.zip',
                        'browser_download_url' => 'https://github.com/owner/repo/releases/download/v2.0.0/test-plugin.zip',
                    ],
                ],
            ] ),
            'https://github.com/owner/repo/releases/download/v2.0.0/test-plugin.zip' => Http::response( 'fake-zip-content' ),
        ] );

        $temporaryArchives = fn (): array => glob( storage_path( 'app/temp/update-*.zip' ) ) ?: [];

        $before = $temporaryArchives();

        expect( fn () => invokeMethod( $this->updateManager, 'downloadUpdateArchive', [
            $plugin,
            [
                'version'      => '2.0.0',
                'download_url' => 'https://github.com/owner/repo/releases/download/v2.0.0/test-plugin.zip',
                'sha256'       => null,
            ],
        ] ) )->toThrow( UpdateException::class );

        expect( $temporaryArchives() )->toBe( $before );
    } );
} );
