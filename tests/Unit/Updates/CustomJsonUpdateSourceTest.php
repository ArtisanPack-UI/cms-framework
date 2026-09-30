<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Sources\CustomJsonUpdateSource;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\MetadataClient;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Custom JSON Update Source Tests
 *
 * @since 1.0.0
 */
class CustomJsonUpdateSourceTest extends TestCase
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
     * Test custom JSON source supports all URLs (fallback source).
     *
     * @since 1.0.0
     */
    public function test_supports_all_urls(): void
    {
        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );

        $this->assertTrue( $source->supports( 'https://example.com/updates.json' ) );
        $this->assertTrue( $source->supports( 'https://github.com/user/repo' ) );
        $this->assertTrue( $source->supports( 'https://gitlab.com/user/repo' ) );
        $this->assertTrue( $source->supports( 'https://anything.com/anything' ) );
    }

    /**
     * Test custom JSON source returns correct name.
     *
     * @since 1.0.0
     */
    public function test_returns_correct_name(): void
    {
        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );

        $this->assertEquals( 'Custom JSON', $source->getName() );
    }

    /**
     * Test custom JSON source can check for updates.
     *
     * @since 1.0.0
     */
    public function test_can_check_for_updates(): void
    {
        Http::fake( [
            'example.com/updates.json' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
                'changelog'    => 'New features',
                'release_date' => '2024-12-15T10:00:00Z',
            ], 200 ),
        ] );

        $source     = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $updateInfo = $source->checkForUpdate();

        $this->assertInstanceOf( UpdateInfo::class, $updateInfo );
        $this->assertEquals( '1.0.0', $updateInfo->currentVersion );
        $this->assertEquals( '2.0.0', $updateInfo->latestVersion );
        $this->assertTrue( $updateInfo->hasUpdate() );
        $this->assertEquals( 'https://example.com/releases/cms-2.0.0.zip', $updateInfo->downloadUrl );
        $this->assertEquals( 'New features', $updateInfo->changelog );
    }

    /**
     * Test custom JSON source throws exception when version is missing.
     *
     * @since 1.0.0
     */
    public function test_throws_exception_when_version_missing(): void
    {
        Http::fake( [
            'example.com/updates.json' => Http::response( [
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );

        $this->expectException( UpdateException::class );
        $this->expectExceptionMessage( 'missing required field: version' );

        $source->checkForUpdate();
    }

    /**
     * Test custom JSON source throws exception when download_url is missing.
     *
     * @since 1.0.0
     */
    public function test_throws_exception_when_download_url_missing(): void
    {
        Http::fake( [
            'example.com/updates.json' => Http::response( [
                'version' => '2.0.0',
            ], 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );

        $this->expectException( UpdateException::class );
        $this->expectExceptionMessage( 'missing required field: download_url' );

        $source->checkForUpdate();
    }

    /**
     * Test custom JSON source handles API errors.
     *
     * @since 1.0.0
     */
    public function test_handles_api_errors(): void
    {
        Http::fake( [
            'example.com/updates.json' => Http::response( [], 500 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );

        $this->expectException( UpdateException::class );
        $this->expectExceptionMessage( 'Failed to check for updates' );

        $source->checkForUpdate();
    }

    /**
     * Test custom JSON source handles invalid JSON.
     *
     * @since 1.0.0
     */
    public function test_handles_invalid_json(): void
    {
        Http::fake( [
            'example.com/updates.json' => Http::response( 'not json', 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );

        $this->expectException( UpdateException::class );
        $this->expectExceptionMessage( 'Invalid JSON response' );

        $source->checkForUpdate();
    }

    /**
     * Test custom JSON source can set authentication with string token.
     *
     * @since 1.0.0
     */
    public function test_can_set_authentication_with_string(): void
    {
        Http::fake( [
            'example.com/updates.json?token=secret123' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( 'secret123' );

        $updateInfo = $source->checkForUpdate();

        $this->assertInstanceOf( UpdateInfo::class, $updateInfo );
    }

    /**
     * Test custom JSON source can set authentication with array.
     *
     * @since 1.0.0
     */
    public function test_can_set_authentication_with_array(): void
    {
        Http::fake( [
            'example.com/updates.json?api_key=key123&license=lic456' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( [
            'api_key' => 'key123',
            'license' => 'lic456',
        ] );

        $updateInfo = $source->checkForUpdate();

        $this->assertInstanceOf( UpdateInfo::class, $updateInfo );
    }

    /**
     * Test custom JSON source handles URLs with existing query params.
     *
     * @since 1.0.0
     */
    public function test_handles_urls_with_existing_query_params(): void
    {
        Http::fake( [
            'example.com/updates.json?existing=param&token=secret123' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json?existing=param', '1.0.0' );
        $source->setAuthentication( 'secret123' );

        $updateInfo = $source->checkForUpdate();

        $this->assertInstanceOf( UpdateInfo::class, $updateInfo );
    }

    /**
     * Test custom JSON source parses all optional fields.
     *
     * @since 1.0.0
     */
    public function test_parses_all_optional_fields(): void
    {
        Http::fake( [
            'example.com/updates.json' => Http::response( [
                'version'               => '2.0.0',
                'download_url'          => 'https://example.com/releases/cms-2.0.0.zip',
                'changelog'             => 'Release notes',
                'release_date'          => '2024-12-15T10:00:00Z',
                'min_php_version'       => '8.2',
                'min_framework_version' => '2.0.0',
                'sha256'                => 'abc123',
                'file_size'             => 1024000,
                'metadata'              => ['custom' => 'data'],
            ], 200 ),
        ] );

        $source     = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $updateInfo = $source->checkForUpdate();

        $this->assertEquals( 'Release notes', $updateInfo->changelog );
        $this->assertEquals( '2024-12-15T10:00:00Z', $updateInfo->releaseDate );
        $this->assertEquals( '8.2', $updateInfo->minPhpVersion );
        $this->assertEquals( '2.0.0', $updateInfo->minFrameworkVersion );
        $this->assertEquals( 'abc123', $updateInfo->sha256 );
        $this->assertEquals( 1024000, $updateInfo->fileSize );
        $this->assertEquals( ['custom' => 'data'], $updateInfo->metadata );
    }

    /**
     * Test custom JSON source detects no update when versions match.
     *
     * @since 1.0.0
     */
    public function test_detects_no_update_when_versions_match(): void
    {
        Http::fake( [
            'example.com/updates.json' => Http::response( [
                'version'      => '1.0.0',
                'download_url' => 'https://example.com/releases/cms-1.0.0.zip',
            ], 200 ),
        ] );

        $source     = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $updateInfo = $source->checkForUpdate();

        $this->assertFalse( $updateInfo->hasUpdate() );
    }

    /**
     * Test custom JSON source streams the download to disk via `sink` rather than
     * buffering the response body in memory.
     *
     * @since 2.5.1
     */
    public function test_download_streams_response_to_sink(): void
    {
        Http::fake( [
            'example.com/updates.json' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
            'example.com/releases/cms-2.0.0.zip' => Http::response( 'zip-bytes', 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );

        $tempPath = $source->downloadUpdate( 'latest' );

        $this->assertFileExists( $tempPath );
        $this->assertSame( 'zip-bytes', file_get_contents( $tempPath ) );

        @unlink( $tempPath );
    }

    /**
     * Test custom JSON source removes the partial download file when the HTTP
     * response is not successful.
     *
     * @since 2.5.1
     */
    public function test_download_cleans_up_partial_file_on_http_failure(): void
    {
        Http::fake( [
            'example.com/updates.json' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
            'example.com/releases/cms-2.0.0.zip' => Http::response( 'partial', 500 ),
        ] );

        $tempDir = storage_path( 'app/temp' );

        if ( is_dir( $tempDir ) ) {
            foreach ( glob( $tempDir . '/update-*.zip' ) ?: [] as $file ) {
                @unlink( $file );
            }
        }

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );

        try {
            $source->downloadUpdate( 'latest' );
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
            'example.com/updates.json' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
            'example.com/releases/cms-2.0.0.zip' => Http::response( 'zip-bytes', 200 ),
        ] );

        $seen = [];
        Event::listen(
            ResponseReceived::class,
            function ( ResponseReceived $event ) use ( &$seen ): void {
                $seen[ $event->request->url() ] = $event->response->body();
            },
        );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );

        $tempPath = $source->downloadUpdate( 'latest' );

        try {
            $this->assertFileExists( $tempPath );
            $this->assertSame( 'zip-bytes', file_get_contents( $tempPath ) );
            $this->assertArrayHasKey( 'https://example.com/releases/cms-2.0.0.zip', $seen );
            $this->assertSame( '', $seen['https://example.com/releases/cms-2.0.0.zip'] );
        } finally {
            @unlink( $tempPath );
        }
    }

    /**
     * Regression for #231: the JSON feed GET must bypass Laravel's HTTP client
     * factory so no `RequestSending` / `ResponseReceived` listener (Herd Pro's
     * `HttpClientWatcher`, Telescope, Debugbar, custom monitoring) can block
     * or corrupt the request lifecycle.
     *
     * @since 2.5.4
     */
    public function test_feed_check_bypasses_laravel_http_client_events(): void
    {
        MetadataClient::reset();

        $mock = new MockHandler( [
            new GuzzleResponse( 200, [], json_encode( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
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

        $source     = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
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
     * The invalid-JSON failure names the feed without its query credentials,
     * since the message is logged and printed by the scheduled check commands.
     *
     * @since 2.12.0
     */
    public function test_invalid_json_message_omits_query_credentials(): void
    {
        Http::fake( [
            'example.com/updates.json?token=secret123' => Http::response( 'not json', 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( 'secret123' );

        try {
            $source->checkForUpdate();
            $this->fail( 'Expected UpdateException to be thrown.' );
        } catch ( UpdateException $e ) {
            $this->assertStringContainsString( 'https://example.com/updates.json', $e->getMessage() );
            $this->assertStringNotContainsString( 'secret123', $e->getMessage() );
        }
    }

    /**
     * Credentials written into the configured feed URL itself are redacted
     * from the invalid-JSON failure too.
     *
     * @since 2.12.0
     */
    public function test_invalid_json_message_redacts_credentials_in_the_configured_url(): void
    {
        Http::fake( [
            'example.com/updates.json*' => Http::response( 'not json', 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://user:hunter2@example.com/updates.json?key=secret123', '1.0.0' );

        try {
            $source->checkForUpdate();
            $this->fail( 'Expected UpdateException to be thrown.' );
        } catch ( UpdateException $e ) {
            $this->assertStringContainsString( 'example.com/updates.json', $e->getMessage() );
            $this->assertStringNotContainsString( 'secret123', $e->getMessage() );
            $this->assertStringNotContainsString( 'hunter2', $e->getMessage() );
        }
    }

    /**
     * Header-mode credentials are sent as request headers, not query params.
     *
     * @since 2.12.0
     */
    public function test_header_authentication_sends_headers_instead_of_query_params(): void
    {
        Http::fake( [
            'example.com/updates.json' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( [
            'headers' => [ 'Authorization' => 'Bearer secret123' ],
        ] );

        $source->checkForUpdate();

        Http::assertSent( fn ( $request ): bool => 'https://example.com/updates.json' === $request->url()
            && $request->hasHeader( 'Authorization', 'Bearer secret123' ) );
    }

    /**
     * Header mode can still carry query parameters through the `query` key.
     *
     * @since 2.12.0
     */
    public function test_header_authentication_can_carry_query_params(): void
    {
        Http::fake( [
            'example.com/updates.json?channel=stable' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( [
            'headers' => [ 'X-License-Key' => 'lic456' ],
            'query'   => [ 'channel' => 'stable' ],
        ] );

        $source->checkForUpdate();

        Http::assertSent( fn ( $request ): bool => 'https://example.com/updates.json?channel=stable' === $request->url()
            && $request->hasHeader( 'X-License-Key', 'lic456' ) );
    }

    /**
     * A header with no usable value is dropped rather than sent empty.
     *
     * @since 2.12.0
     */
    public function test_header_authentication_drops_empty_header_values(): void
    {
        Http::fake( [
            'example.com/updates.json' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( [
            'headers' => [
                'X-License-Key' => null,
                'X-Site'        => '  ',
                'X-Channel'     => 'stable',
            ],
        ] );

        $source->checkForUpdate();

        Http::assertSent( fn ( $request ): bool => ! $request->hasHeader( 'X-License-Key' )
            && ! $request->hasHeader( 'X-Site' )
            && $request->hasHeader( 'X-Channel', 'stable' ) );
    }

    /**
     * The archive download carries the feed's auth headers when it stays on
     * the feed's own origin.
     *
     * @since 2.12.0
     */
    public function test_download_sends_auth_headers_to_the_feed_origin(): void
    {
        Http::fake( [
            'example.com/updates.json' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
            'example.com/releases/cms-2.0.0.zip' => Http::response( 'zip-bytes', 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( [
            'headers' => [ 'Authorization' => 'Bearer secret123' ],
        ] );

        $tempPath = $source->downloadUpdate( 'latest' );

        @unlink( $tempPath );

        Http::assertSent( fn ( $request ): bool => 'https://example.com/releases/cms-2.0.0.zip' === $request->url()
            && $request->hasHeader( 'Authorization', 'Bearer secret123' ) );
    }

    /**
     * A `download_url` off the feed's origin — another host, another port, or
     * a plaintext downgrade — never receives the feed's credential.
     *
     * @since 2.12.0
     */
    #[DataProvider( 'crossOriginDownloadUrls' )]
    public function test_download_withholds_auth_headers_from_other_origins( string $downloadUrl ): void
    {
        config()->set( 'cms.updates.allow_insecure_transport', true );

        Http::fake( [
            'example.com/updates.json' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => $downloadUrl,
            ], 200 ),
            '*' => Http::response( 'zip-bytes', 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( [
            'headers' => [
                'Authorization' => 'Bearer secret123',
                'X-License-Key' => 'lic456',
            ],
        ] );

        $tempPath = $source->downloadUpdate( 'latest' );

        @unlink( $tempPath );

        Http::assertSent( fn ( $request ): bool => $downloadUrl === $request->url()
            && ! $request->hasHeader( 'Authorization' )
            && ! $request->hasHeader( 'X-License-Key' ) );
    }

    /**
     * Download URLs that leave the `https://example.com` feed origin.
     *
     * @since 2.12.0
     *
     * @return array<string, array{string}>
     */
    public static function crossOriginDownloadUrls(): array
    {
        return [
            'another host'        => [ 'https://cdn.example.net/releases/cms-2.0.0.zip' ],
            'a subdomain'         => [ 'https://downloads.example.com/cms-2.0.0.zip' ],
            'another port'        => [ 'https://example.com:8443/releases/cms-2.0.0.zip' ],
            'plaintext downgrade' => [ 'http://example.com/releases/cms-2.0.0.zip' ],
        ];
    }

    /**
     * A redirect off the feed's origin drops the credential for that hop,
     * including custom headers the HTTP client would otherwise forward.
     *
     * @since 2.12.0
     */
    public function test_download_drops_auth_headers_on_cross_origin_redirect(): void
    {
        Http::fake( [
            'example.com/updates.json' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
            'example.com/releases/cms-2.0.0.zip' => Http::response( '', 302, [
                'Location' => 'https://cdn.example.net/cms-2.0.0.zip',
            ] ),
            'cdn.example.net/cms-2.0.0.zip' => Http::response( 'zip-bytes', 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( [
            'headers' => [ 'X-License-Key' => 'lic456' ],
        ] );

        $tempPath = $source->downloadUpdate( 'latest' );

        $this->assertSame( 'zip-bytes', file_get_contents( $tempPath ) );

        @unlink( $tempPath );

        Http::assertSent( fn ( $request ): bool => 'https://example.com/releases/cms-2.0.0.zip' === $request->url()
            && $request->hasHeader( 'X-License-Key', 'lic456' ) );
        Http::assertSent( fn ( $request ): bool => 'https://cdn.example.net/cms-2.0.0.zip' === $request->url()
            && ! $request->hasHeader( 'X-License-Key' ) );
    }

    /**
     * Query-string credentials are never forwarded to the download URL.
     *
     * @since 2.12.0
     */
    public function test_download_does_not_forward_query_credentials(): void
    {
        Http::fake( [
            'example.com/updates.json?token=secret123' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
            'example.com/releases/cms-2.0.0.zip' => Http::response( 'zip-bytes', 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( 'secret123' );

        $tempPath = $source->downloadUpdate( 'latest' );

        @unlink( $tempPath );

        Http::assertSent( fn ( $request ): bool => 'https://example.com/releases/cms-2.0.0.zip' === $request->url()
            && ! $request->hasHeader( 'Authorization' ) );
    }

    /**
     * Switching back to a query-string credential clears an earlier header
     * credential instead of leaving it in force.
     *
     * @since 2.12.0
     */
    #[DataProvider( 'queryModeCredentials' )]
    public function test_query_mode_credentials_clear_previous_headers( string|array $credentials ): void
    {
        Http::fake( [
            '*' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( [
            'headers' => [ 'Authorization' => 'Bearer secret123' ],
        ] );
        $source->setAuthentication( $credentials );

        $source->checkForUpdate();

        Http::assertSent( fn ( $request ): bool => ! $request->hasHeader( 'Authorization' ) );
    }

    /**
     * Credentials that select a query-string mode.
     *
     * @since 2.12.0
     *
     * @return array<string, array{array<string, string>|string}>
     */
    public static function queryModeCredentials(): array
    {
        return [
            'a token string' => [ 'new-token' ],
            'a flat array'   => [ [ 'api_key' => 'key123' ] ],
            'an empty array' => [ [] ],
        ];
    }

    /**
     * Credential headers are never sent to a plaintext feed.
     *
     * @since 2.12.0
     */
    public function test_header_authentication_refuses_a_plaintext_feed(): void
    {
        Http::fake();

        $source = new CustomJsonUpdateSource( 'http://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( [
            'headers' => [ 'Authorization' => 'Bearer secret123' ],
        ] );

        try {
            $source->checkForUpdate();
            $this->fail( 'Expected UpdateException to be thrown.' );
        } catch ( UpdateException $e ) {
            $this->assertStringContainsString( 'insecure transport', $e->getMessage() );
        }

        Http::assertNothingSent();
    }

    /**
     * The insecure-transport opt-out applies to a credentialed feed as it does
     * to the archive download.
     *
     * @since 2.12.0
     */
    public function test_header_authentication_allows_a_plaintext_feed_when_opted_in(): void
    {
        config()->set( 'cms.updates.allow_insecure_transport', true );

        Http::fake( [
            'example.com/updates.json' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'http://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'http://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( [
            'headers' => [ 'Authorization' => 'Bearer secret123' ],
        ] );

        $this->assertSame( '2.0.0', $source->checkForUpdate()->latestVersion );
    }

    /**
     * A credentialed feed request does not follow redirects, so a custom
     * header is never passed along to the host a redirect names.
     *
     * @since 2.12.0
     */
    public function test_header_authentication_does_not_follow_feed_redirects(): void
    {
        Http::fake( [
            'example.com/updates.json' => Http::response( '', 302, [
                'Location' => 'https://evil.example.net/updates.json',
            ] ),
            'evil.example.net/*' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://evil.example.net/cms-2.0.0.zip',
            ], 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( [
            'headers' => [ 'X-License-Key' => 'lic456' ],
        ] );

        try {
            $source->checkForUpdate();
            $this->fail( 'Expected UpdateException to be thrown.' );
        } catch ( UpdateException $e ) {
            $this->assertStringContainsString( 'redirects are not followed', $e->getMessage() );
        }

        Http::assertSentCount( 1 );
        Http::assertNotSent( fn ( $request ): bool => str_contains( $request->url(), 'evil.example.net' ) );
    }

    /**
     * The production (raw Guzzle) path disables redirects for a credentialed
     * feed request rather than relying on the test bridge.
     *
     * @since 2.12.0
     */
    public function test_header_authentication_disables_redirects_on_the_raw_client(): void
    {
        MetadataClient::reset();

        $mock = new MockHandler( [
            new GuzzleResponse( 302, [ 'Location' => 'https://evil.example.net/updates.json' ] ),
            new GuzzleResponse( 200, [], json_encode( [
                'version'      => '2.0.0',
                'download_url' => 'https://evil.example.net/cms-2.0.0.zip',
            ] ) ),
        ] );
        MetadataClient::setClient( new GuzzleClient( [ 'handler' => HandlerStack::create( $mock ) ] ) );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( [
            'headers' => [ 'X-License-Key' => 'lic456' ],
        ] );

        try {
            $source->checkForUpdate();
            $this->fail( 'Expected UpdateException to be thrown.' );
        } catch ( UpdateException $e ) {
            $this->assertStringContainsString( 'redirects are not followed', $e->getMessage() );
        }

        $this->assertSame( 1, $mock->count(), 'The redirect target must never be requested.' );
    }

    /**
     * A feed without credential headers still follows redirects.
     *
     * @since 2.12.0
     */
    public function test_feed_without_header_credentials_still_follows_redirects(): void
    {
        Http::fake( [
            'example.com/updates.json' => Http::response( '', 302, [
                'Location' => 'https://example.com/v2/updates.json',
            ] ),
            'example.com/v2/updates.json' => Http::response( [
                'version'      => '2.0.0',
                'download_url' => 'https://example.com/releases/cms-2.0.0.zip',
            ], 200 ),
        ] );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );

        $this->assertSame( '2.0.0', $source->checkForUpdate()->latestVersion );
    }

    /**
     * A transport failure reports the host but not the query-string token the
     * HTTP client appends to its error message.
     *
     * @since 2.12.0
     */
    public function test_transport_error_message_omits_url_credentials(): void
    {
        MetadataClient::reset();

        $failure = static fn (): ConnectException => new ConnectException(
            'cURL error 6: Could not resolve host: example.com for https://user:hunter2@example.com/updates.json?token=secret123',
            new GuzzleRequest( 'GET', 'https://example.com/updates.json?token=secret123' ),
        );
        $mock = new MockHandler( [ $failure(), $failure(), $failure() ] );
        MetadataClient::setClient( new GuzzleClient( [ 'handler' => HandlerStack::create( $mock ) ] ) );

        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );
        $source->setAuthentication( 'secret123' );

        try {
            $source->checkForUpdate();
            $this->fail( 'Expected UpdateException to be thrown.' );
        } catch ( UpdateException $e ) {
            $this->assertStringContainsString( 'Could not resolve host: example.com', $e->getMessage() );
            $this->assertStringContainsString( 'example.com/updates.json', $e->getMessage() );
            $this->assertStringNotContainsString( 'secret123', $e->getMessage() );
            $this->assertStringNotContainsString( 'hunter2', $e->getMessage() );
        }
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
        $app['config']->set( 'cms.updates.http_retries', 3 );
        $app['config']->set( 'cms.updates.download_timeout', 300 );
    }
}
