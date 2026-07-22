<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Update Info Tests
 *
 * @since 1.0.0
 */
class UpdateInfoTest extends TestCase
{
    /**
     * Snapshot of the global container instance before each test so we can
     * restore it in tearDown. Without this, a test that calls
     * `Container::setInstance()` (see the #226 regressions below) can leak the
     * hand-built container into any subsequent test class that assumes the
     * container Testbench set up is still current.
     *
     * @since 2.5.3
     */
    private ?Container $containerSnapshot = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->containerSnapshot = Container::getInstance();
    }

    protected function tearDown(): void
    {
        Container::setInstance( $this->containerSnapshot );

        parent::tearDown();
    }

    /**
     * Test UpdateInfo can be instantiated.
     *
     * @since 1.0.0
     */
    public function test_can_create_update_info(): void
    {
        $info = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            downloadUrl: 'https://example.com/update.zip',
        );

        $this->assertEquals( '1.0.0', $info->currentVersion );
        $this->assertEquals( '2.0.0', $info->latestVersion );
        $this->assertTrue( $info->hasUpdate() );
        $this->assertEquals( 'https://example.com/update.zip', $info->downloadUrl );
    }

    /**
     * Test UpdateInfo can be created from array.
     *
     * @since 1.0.0
     */
    public function test_can_create_from_array(): void
    {
        $data = [
            'version'      => '2.0.0',
            'download_url' => 'https://example.com/update.zip',
            'changelog'    => 'New features',
            'sha256'       => 'abc123',
        ];

        $info = UpdateInfo::fromArray( $data, '1.0.0' );

        $this->assertEquals( '1.0.0', $info->currentVersion );
        $this->assertEquals( '2.0.0', $info->latestVersion );
        $this->assertTrue( $info->hasUpdate() );
        $this->assertEquals( 'https://example.com/update.zip', $info->downloadUrl );
        $this->assertEquals( 'New features', $info->changelog );
        $this->assertEquals( 'abc123', $info->sha256 );
    }

    /**
     * Test UpdateInfo detects no update when versions match.
     *
     * @since 1.0.0
     */
    public function test_detects_no_update_when_versions_match(): void
    {
        $data = [
            'version'      => '1.0.0',
            'download_url' => 'https://example.com/update.zip',
        ];

        $info = UpdateInfo::fromArray( $data, '1.0.0' );

        $this->assertFalse( $info->hasUpdate() );
    }

    /**
     * Test UpdateInfo detects no update when current is newer.
     *
     * @since 1.0.0
     */
    public function test_detects_no_update_when_current_is_newer(): void
    {
        $data = [
            'version'      => '1.0.0',
            'download_url' => 'https://example.com/update.zip',
        ];

        $info = UpdateInfo::fromArray( $data, '2.0.0' );

        $this->assertFalse( $info->hasUpdate() );
    }

    /**
     * Regression for #226: `hasUpdate()` compares `latestVersion` against
     * `config('app.version')` at call time when the container is bootstrapped
     * and the value is set, so an out-of-band version bump (manual composer
     * install, deploy script) invalidates the stale positive from a cached
     * `UpdateInfo` snapshot.
     *
     * @since 2.5.3
     */
    public function test_has_update_reads_current_version_from_config_when_available(): void
    {
        $container = new Container;
        $container->instance( 'config', new ConfigRepository( [
            'app' => ['version' => '0.2.2'],
        ] ) );
        Container::setInstance( $container );

        $stale = new UpdateInfo(
            currentVersion: '0.2.0',
            latestVersion: '0.2.2',
            downloadUrl: 'https://example.com/update.zip',
        );

        $this->assertFalse(
            $stale->hasUpdate(),
            'Expected fresh app.version to override the cached currentVersion snapshot.',
        );
    }

    /**
     * Regression for #226: when the container has no bound `config`,
     * `hasUpdate()` falls back to the constructor `currentVersion` so
     * pre-container / non-Laravel callers still see the pre-2.5.3 comparison.
     *
     * @since 2.5.3
     */
    public function test_has_update_falls_back_to_snapshot_when_config_unbound(): void
    {
        Container::setInstance( null );

        $info = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            downloadUrl: 'https://example.com/update.zip',
        );

        $this->assertTrue( $info->hasUpdate() );
    }

    /**
     * Regression for #226: when `app.version` is unset on a host that never
     * configured it (or set it to an empty string), `hasUpdate()` still falls
     * back to the constructor snapshot rather than treating the "no fresh
     * version to compare against" case as a valid comparison. Otherwise the
     * fresh-read guard silently degrades to the pre-fix behavior when it
     * matters most.
     *
     * @since 2.5.3
     */
    public function test_has_update_falls_back_to_snapshot_when_app_version_missing(): void
    {
        $container = new Container;
        $container->instance( 'config', new ConfigRepository( [
            'app' => [],
        ] ) );
        Container::setInstance( $container );

        $info = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            downloadUrl: 'https://example.com/update.zip',
        );

        $this->assertTrue(
            $info->hasUpdate(),
            'Expected snapshot fallback when app.version is unset.',
        );
    }

    /**
     * Test UpdateInfo can be converted to array.
     *
     * @since 1.0.0
     */
    /**
     * Regression for #226: `toArray()['current']` must reflect the fresh
     * `app.version` rather than the frozen constructor snapshot, so JSON
     * consumers (and the console commands that print it) see a consistent
     * `{ current, latest, hasUpdate }` triple after an out-of-band version
     * bump.
     *
     * @since 2.5.3
     */
    public function test_to_array_uses_fresh_current_version_when_config_available(): void
    {
        $container = new Container;
        $container->instance( 'config', new ConfigRepository( [
            'app' => ['version' => '0.2.2'],
        ] ) );
        Container::setInstance( $container );

        $stale = new UpdateInfo(
            currentVersion: '0.2.0',
            latestVersion: '0.2.2',
            downloadUrl: 'https://example.com/update.zip',
        );

        $array = $stale->toArray();

        $this->assertSame( '0.2.2', $array['current'] );
        $this->assertSame( '0.2.2', $array['latest'] );
        $this->assertFalse( $array['hasUpdate'] );
    }

    /**
     * Test UpdateInfo can be converted to array.
     *
     * @since 1.0.0
     */
    public function test_can_convert_to_array(): void
    {
        $info = new UpdateInfo(
            currentVersion: '1.0.0',
            latestVersion: '2.0.0',
            downloadUrl: 'https://example.com/update.zip',
            changelog: 'New features',
        );

        $array = $info->toArray();

        $this->assertArrayHasKey( 'current', $array );
        $this->assertArrayHasKey( 'latest', $array );
        $this->assertArrayHasKey( 'hasUpdate', $array );
        $this->assertArrayHasKey( 'download_url', $array );
        $this->assertArrayHasKey( 'changelog', $array );

        $this->assertEquals( '1.0.0', $array['current'] );
        $this->assertEquals( '2.0.0', $array['latest'] );
        $this->assertTrue( $array['hasUpdate'] );
        $this->assertEquals( 'https://example.com/update.zip', $array['download_url'] );
        $this->assertEquals( 'New features', $array['changelog'] );
    }
}
