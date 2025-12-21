<?php

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Exceptions\UpdateException;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Sources\CustomJsonUpdateSource;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

/**
 * Custom JSON Update Source Tests
 *
 * @since 2.0.0
 */
class CustomJsonUpdateSourceTest extends TestCase
{
    /**
     * Test custom JSON source supports all URLs (fallback source).
     *
     * @since 2.0.0
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
     * @since 2.0.0
     */
    public function test_returns_correct_name(): void
    {
        $source = new CustomJsonUpdateSource( 'https://example.com/updates.json', '1.0.0' );

        $this->assertEquals( 'Custom JSON', $source->getName() );
    }

    /**
     * Test custom JSON source can check for updates.
     *
     * @since 2.0.0
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
        $this->assertTrue( $updateInfo->hasUpdate );
        $this->assertEquals( 'https://example.com/releases/cms-2.0.0.zip', $updateInfo->downloadUrl );
        $this->assertEquals( 'New features', $updateInfo->changelog );
    }

    /**
     * Test custom JSON source throws exception when version is missing.
     *
     * @since 2.0.0
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
     * @since 2.0.0
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
     * @since 2.0.0
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
     * @since 2.0.0
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
     * @since 2.0.0
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
     * @since 2.0.0
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
     * @since 2.0.0
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
     * @since 2.0.0
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
     * @since 2.0.0
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

        $this->assertFalse( $updateInfo->hasUpdate );
    }

    /**
     * Define environment setup.
     *
     * @since 2.0.0
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
