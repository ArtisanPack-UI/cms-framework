<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Sources\GitHubUpdateSource;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\MetadataClient;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;

/**
 * GitHub Update Source Tests
 *
 * @since 1.0.0
 */
class GitHubUpdateSourceTest extends TestCase
{
    /**
     * @since 2.5.4
     */
    protected function setUp(): void
    {
        parent::setUp();

        MetadataClient::useHttpFacadeBridge();
    }

    /**
     * @since 2.5.4
     */
    protected function tearDown(): void
    {
        MetadataClient::reset();

        parent::tearDown();
    }

    /**
     * Test GitHub source supports GitHub URLs.
     *
     * @since 1.0.0
     */
    public function test_supports_github_urls(): void
    {
        $source = new GitHubUpdateSource( 'https://github.com/user/repo', '1.0.0' );

        $this->assertTrue( $source->supports( 'https://github.com/user/repo' ) );
        $this->assertTrue( $source->supports( 'https://github.com/another-user/another-repo' ) );
        $this->assertFalse( $source->supports( 'https://gitlab.com/user/repo' ) );
        $this->assertFalse( $source->supports( 'https://example.com/updates.json' ) );
        $this->assertFalse( $source->supports( 'https://example.com/github.com/user/repo' ) );
    }

    /**
     * Test GitHub source returns correct name.
     *
     * @since 1.0.0
     */
    public function test_returns_correct_name(): void
    {
        $source = new GitHubUpdateSource( 'https://github.com/user/repo', '1.0.0' );

        $this->assertEquals( 'GitHub', $source->getName() );
    }

    /**
     * Test GitHub source can check for updates.
     *
     * @since 1.0.0
     */
    public function test_can_check_for_updates(): void
    {
        Http::fake( [
            'api.github.com/repos/user/repo/releases' => Http::response( [
                [
                    'tag_name'     => 'v2.0.0',
                    'prerelease'   => false,
                    'body'         => 'Release notes here',
                    'published_at' => '2024-12-15T10:00:00Z',
                    'html_url'     => 'https://github.com/user/repo/releases/tag/v2.0.0',
                    'id'           => 123,
                    'assets'       => [
                        [
                            'name'                 => 'repo.zip',
                            'browser_download_url' => 'https://github.com/user/repo/releases/download/v2.0.0/repo.zip',
                        ],
                    ],
                    'zipball_url' => 'https://api.github.com/repos/user/repo/zipball/v2.0.0',
                ],
            ], 200 ),
        ] );

        $source     = new GitHubUpdateSource( 'https://github.com/user/repo', '1.0.0' );
        $updateInfo = $source->checkForUpdate();

        $this->assertInstanceOf( UpdateInfo::class, $updateInfo );
        $this->assertEquals( '1.0.0', $updateInfo->currentVersion );
        $this->assertEquals( '2.0.0', $updateInfo->latestVersion );
        $this->assertTrue( $updateInfo->hasUpdate() );
        $this->assertStringContainsString( 'github.com', $updateInfo->downloadUrl );
        $this->assertEquals( 'Release notes here', $updateInfo->changelog );
    }

    /**
     * Test GitHub source handles no releases.
     *
     * @since 1.0.0
     */
    public function test_throws_exception_when_no_releases(): void
    {
        Http::fake( [
            'api.github.com/repos/user/repo/releases' => Http::response( [], 200 ),
        ] );

        $source = new GitHubUpdateSource( 'https://github.com/user/repo', '1.0.0' );

        $this->expectException( UpdateException::class );
        $this->expectExceptionMessage( 'No releases found' );

        $source->checkForUpdate();
    }

    /**
     * Test GitHub source skips prerelease versions.
     *
     * @since 1.0.0
     */
    public function test_skips_prerelease_versions(): void
    {
        Http::fake( [
            'api.github.com/repos/user/repo/releases' => Http::response( [
                [
                    'tag_name'     => 'v3.0.0-beta',
                    'prerelease'   => true,
                    'body'         => 'Beta release',
                    'published_at' => '2024-12-20T10:00:00Z',
                    'html_url'     => 'https://github.com/user/repo/releases/tag/v3.0.0-beta',
                    'id'           => 124,
                    'assets'       => [],
                    'zipball_url'  => 'https://api.github.com/repos/user/repo/zipball/v3.0.0-beta',
                ],
                [
                    'tag_name'     => 'v2.0.0',
                    'prerelease'   => false,
                    'body'         => 'Stable release',
                    'published_at' => '2024-12-15T10:00:00Z',
                    'html_url'     => 'https://github.com/user/repo/releases/tag/v2.0.0',
                    'id'           => 123,
                    'assets'       => [],
                    'zipball_url'  => 'https://api.github.com/repos/user/repo/zipball/v2.0.0',
                ],
            ], 200 ),
        ] );

        $source     = new GitHubUpdateSource( 'https://github.com/user/repo', '1.0.0' );
        $updateInfo = $source->checkForUpdate();

        $this->assertEquals( '2.0.0', $updateInfo->latestVersion );
    }

    /**
     * Test GitHub source falls back to zipball_url when no assets.
     *
     * @since 1.0.0
     */
    public function test_falls_back_to_zipball_when_no_assets(): void
    {
        Http::fake( [
            'api.github.com/repos/user/repo/releases' => Http::response( [
                [
                    'tag_name'     => 'v2.0.0',
                    'prerelease'   => false,
                    'body'         => 'Release',
                    'published_at' => '2024-12-15T10:00:00Z',
                    'html_url'     => 'https://github.com/user/repo/releases/tag/v2.0.0',
                    'id'           => 123,
                    'assets'       => [],
                    'zipball_url'  => 'https://api.github.com/repos/user/repo/zipball/v2.0.0',
                ],
            ], 200 ),
        ] );

        $source     = new GitHubUpdateSource( 'https://github.com/user/repo', '1.0.0' );
        $updateInfo = $source->checkForUpdate();

        $this->assertStringContainsString( 'zipball', $updateInfo->downloadUrl );
    }

    /**
     * Test GitHub source handles API errors.
     *
     * @since 1.0.0
     */
    public function test_handles_api_errors(): void
    {
        Http::fake( [
            'api.github.com/repos/user/repo/releases' => Http::response( [], 500 ),
        ] );

        $source = new GitHubUpdateSource( 'https://github.com/user/repo', '1.0.0' );

        $this->expectException( UpdateException::class );
        $this->expectExceptionMessage( 'GitHub API error' );

        $source->checkForUpdate();
    }

    /**
     * Test GitHub source can set authentication.
     *
     * @since 1.0.0
     */
    public function test_can_set_authentication(): void
    {
        Http::fake( [
            'api.github.com/repos/user/repo/releases' => Http::response( [
                [
                    'tag_name'     => 'v2.0.0',
                    'prerelease'   => false,
                    'body'         => 'Release',
                    'published_at' => '2024-12-15T10:00:00Z',
                    'html_url'     => 'https://github.com/user/repo/releases/tag/v2.0.0',
                    'id'           => 123,
                    'assets'       => [],
                    'zipball_url'  => 'https://api.github.com/repos/user/repo/zipball/v2.0.0',
                ],
            ], 200 ),
        ] );

        $source = new GitHubUpdateSource( 'https://github.com/user/repo', '1.0.0' );
        $source->setAuthentication( 'ghp_test_token' );

        $source->checkForUpdate();

        Http::assertSent( function ( $request ) {
            return $request->hasHeader( 'Authorization', 'token ghp_test_token' );
        } );
    }

    /**
     * Test GitHub source parses repository owner and name correctly.
     *
     * @since 1.0.0
     */
    public function test_parses_repository_url_correctly(): void
    {
        Http::fake( [
            'api.github.com/repos/test-owner/test-repo/releases' => Http::response( [
                [
                    'tag_name'     => 'v1.0.0',
                    'prerelease'   => false,
                    'published_at' => '2024-12-15T10:00:00Z',
                    'html_url'     => 'https://github.com/test-owner/test-repo/releases/tag/v1.0.0',
                    'id'           => 123,
                    'assets'       => [],
                    'zipball_url'  => 'https://api.github.com/repos/test-owner/test-repo/zipball/v1.0.0',
                ],
            ], 200 ),
        ] );

        $source     = new GitHubUpdateSource( 'https://github.com/test-owner/test-repo', '0.9.0' );
        $updateInfo = $source->checkForUpdate();

        // If we get here without exception, the URL was parsed correctly
        $this->assertInstanceOf( UpdateInfo::class, $updateInfo );
    }

    /**
     * Test GitHub source throws exception for invalid URLs.
     *
     * @since 1.0.0
     */
    public function test_throws_exception_for_invalid_urls(): void
    {
        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessage( 'Invalid GitHub URL' );

        new GitHubUpdateSource( 'https://invalid-url.com', '1.0.0' );
    }

    /**
     * Test GitHub source strips 'v' prefix from version tags.
     *
     * @since 1.0.0
     */
    public function test_strips_v_prefix_from_version_tags(): void
    {
        Http::fake( [
            'api.github.com/repos/user/repo/releases' => Http::response( [
                [
                    'tag_name'     => 'v2.5.1',
                    'prerelease'   => false,
                    'published_at' => '2024-12-15T10:00:00Z',
                    'html_url'     => 'https://github.com/user/repo/releases/tag/v2.5.1',
                    'id'           => 123,
                    'assets'       => [],
                    'zipball_url'  => 'https://api.github.com/repos/user/repo/zipball/v2.5.1',
                ],
            ], 200 ),
        ] );

        $source     = new GitHubUpdateSource( 'https://github.com/user/repo', '1.0.0' );
        $updateInfo = $source->checkForUpdate();

        $this->assertEquals( '2.5.1', $updateInfo->latestVersion );
    }

    /**
     * Test GitHub source streams the download to disk via `sink` rather than
     * buffering the response body in memory.
     *
     * @since 2.5.1
     */
    public function test_download_streams_response_to_sink(): void
    {
        Http::fake( [
            'api.github.com/repos/user/repo/releases/tags/v2.0.0' => Http::response( [
                'tag_name'    => 'v2.0.0',
                'prerelease'  => false,
                'zipball_url' => 'https://api.github.com/repos/user/repo/zipball/v2.0.0',
                'assets'      => [
                    [
                        'name'                 => 'app-2.0.0.zip',
                        'browser_download_url' => 'https://example.com/app-2.0.0.zip',
                    ],
                ],
            ], 200 ),
            'example.com/app-2.0.0.zip' => Http::response( 'zip-bytes', 200 ),
        ] );

        $source = new GitHubUpdateSource( 'https://github.com/user/repo', '1.0.0' );

        $tempPath = $source->downloadUpdate( 'v2.0.0' );

        $this->assertFileExists( $tempPath );
        $this->assertSame( 'zip-bytes', file_get_contents( $tempPath ) );

        @unlink( $tempPath );
    }

    /**
     * Test GitHub source removes the partial download file when the HTTP
     * response is not successful.
     *
     * @since 2.5.1
     */
    public function test_download_cleans_up_partial_file_on_http_failure(): void
    {
        Http::fake( [
            'api.github.com/repos/user/repo/releases/tags/v2.0.0' => Http::response( [
                'tag_name'    => 'v2.0.0',
                'prerelease'  => false,
                'zipball_url' => 'https://api.github.com/repos/user/repo/zipball/v2.0.0',
                'assets'      => [
                    [
                        'name'                 => 'app-2.0.0.zip',
                        'browser_download_url' => 'https://example.com/app-2.0.0.zip',
                    ],
                ],
            ], 200 ),
            'example.com/app-2.0.0.zip' => Http::response( 'partial', 500 ),
        ] );

        $tempDir = storage_path( 'app/temp' );

        if ( is_dir( $tempDir ) ) {
            foreach ( glob( $tempDir . '/update-*.zip' ) ?: [] as $file ) {
                @unlink( $file );
            }
        }

        $source = new GitHubUpdateSource( 'https://github.com/user/repo', '1.0.0' );

        try {
            $source->downloadUpdate( 'v2.0.0' );
            $this->fail( 'Expected UpdateException to be thrown.' );
        } catch ( UpdateException $e ) {
            $leftover = is_dir( $tempDir ) ? glob( $tempDir . '/update-*.zip' ) : [];
            $this->assertSame( [], $leftover, 'Expected partial download to be removed on failure.' );
        }
    }

    /**
     * Regression for #219 / #224: after the download returns, the response body
     * must be safely inspectable by downstream `ResponseReceived` listeners
     * (Herd Pro's HTTP watcher, Telescope, Debugbar, custom monitoring). The
     * body is swapped for a fresh empty stream so `->body()` returns `''`
     * instead of copying the release archive back into a PHP string (#219) or
     * throwing "Stream is detached" on the closed sink stream (#224).
     *
     * @since 2.5.2
     */
    public function test_download_response_body_is_observer_safe(): void
    {
        Http::fake( [
            'api.github.com/repos/user/repo/releases/tags/v2.0.0' => Http::response( [
                'tag_name'    => 'v2.0.0',
                'prerelease'  => false,
                'zipball_url' => 'https://api.github.com/repos/user/repo/zipball/v2.0.0',
                'assets'      => [
                    [
                        'name'                 => 'app-2.0.0.zip',
                        'browser_download_url' => 'https://example.com/app-2.0.0.zip',
                    ],
                ],
            ], 200 ),
            'example.com/app-2.0.0.zip' => Http::response( 'zip-bytes', 200 ),
        ] );

        $seen = [];
        Event::listen(
            ResponseReceived::class,
            function ( ResponseReceived $event ) use ( &$seen ): void {
                $seen[ $event->request->url() ] = $event->response->body();
            },
        );

        $source = new GitHubUpdateSource( 'https://github.com/user/repo', '1.0.0' );

        $tempPath = $source->downloadUpdate( 'v2.0.0' );

        try {
            $this->assertFileExists( $tempPath );
            $this->assertSame( 'zip-bytes', file_get_contents( $tempPath ) );
            $this->assertArrayHasKey( 'https://example.com/app-2.0.0.zip', $seen );
            $this->assertSame( '', $seen['https://example.com/app-2.0.0.zip'] );
        } finally {
            @unlink( $tempPath );
        }
    }

    /**
     * Regression for #231: the feed-check GET must bypass Laravel's HTTP
     * client factory so no `RequestSending` / `ResponseReceived` listener
     * (Herd Pro's `HttpClientWatcher`, Telescope, Debugbar, custom monitoring)
     * can block or corrupt the request lifecycle.
     *
     * @since 2.5.4
     */
    public function test_feed_check_bypasses_laravel_http_client_events(): void
    {
        MetadataClient::reset();

        $mock = new MockHandler( [
            new GuzzleResponse( 200, [], json_encode( [
                [
                    'tag_name'     => 'v2.0.0',
                    'name'         => 'v2.0.0',
                    'body'         => 'Release',
                    'html_url'     => 'https://github.com/user/repo/releases/tag/v2.0.0',
                    'id'           => 1,
                    'prerelease'   => false,
                    'published_at' => '2024-12-15T10:00:00Z',
                    'assets'       => [],
                    'zipball_url'  => 'https://api.github.com/repos/user/repo/zipball/v2.0.0',
                ],
            ] ) ),
        ] );
        MetadataClient::setClient( new GuzzleClient( [ 'handler' => HandlerStack::create( $mock ) ] ) );

        $requestSendingFired   = false;
        $responseReceivedFired = false;
        Event::listen( RequestSending::class, function () use ( &$requestSendingFired ): void {
            $requestSendingFired = true;
        } );
        Event::listen( ResponseReceived::class, function () use ( &$responseReceivedFired ): void {
            $responseReceivedFired = true;
        } );

        $source     = new GitHubUpdateSource( 'https://github.com/user/repo', '1.0.0' );
        $updateInfo = $source->checkForUpdate();

        $this->assertSame( '2.0.0', $updateInfo->latestVersion );
        $this->assertFalse(
            $requestSendingFired,
            'RequestSending must not fire for the feed check — any userland listener would block the request lifecycle (see #231).',
        );
        $this->assertFalse(
            $responseReceivedFired,
            'ResponseReceived must not fire for the feed check — any userland listener would block the request lifecycle (see #231).',
        );
    }

    /**
     * Define environment setup.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment( $app ): void
    {
        $app['config']->set( 'cms.updates.http_timeout', 15 );
        $app['config']->set( 'cms.updates.download_timeout', 300 );
    }
}
