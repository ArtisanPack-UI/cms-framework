<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Contracts\UpdateSourceInterface;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateType;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\UpdateChecker;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\ValueObjects\UpdateInfo;
use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase;

/**
 * Update Checker Tests
 *
 * @since 2.5.3
 */
class UpdateCheckerTest extends TestCase
{
    /**
     * Regression for #226: `UpdateChecker::checkForUpdate()` discards a cached
     * `UpdateInfo` whose `currentVersion` snapshot no longer matches
     * `config('app.version')`, forcing a fresh source check. Without this,
     * an out-of-band version bump leaves the cache serving a stale
     * "update available to X" for a site already on X, up to
     * `cms.updates.cache_ttl` seconds.
     *
     * @since 2.5.3
     */
    public function test_check_for_update_discards_cache_when_current_version_moved(): void
    {
        config( ['app.version' => '0.2.2'] );

        $cacheKey = 'cms.' . UpdateType::Application->value . '.test-app.update_check';

        // Prime the cache with a snapshot from when the host was on 0.2.0.
        Cache::put(
            $cacheKey,
            new UpdateInfo(
                currentVersion: '0.2.0',
                latestVersion: '0.2.2',
                downloadUrl: 'https://example.com/update.zip',
            ),
            3600,
        );

        $freshInfo = new UpdateInfo(
            currentVersion: '0.2.2',
            latestVersion: '0.2.2',
            downloadUrl: 'https://example.com/update.zip',
        );

        $source = new class( $freshInfo ) implements UpdateSourceInterface {
            public int $checkCount = 0;

            public function __construct( protected UpdateInfo $info )
            {
            }

            public function supports( string $url ): bool
            {
                return true;
            }

            public function checkForUpdate(): UpdateInfo
            {
                $this->checkCount++;

                return $this->info;
            }

            public function downloadUpdate( string $version ): string
            {
                return '/tmp/noop.zip';
            }

            public function setAuthentication( string|array $credentials ): void
            {
            }

            public function getName(): string
            {
                return 'Fake';
            }
        };

        $checker = new UpdateChecker( $source, UpdateType::Application, 'test-app' );

        $result = $checker->checkForUpdate();

        $this->assertSame( 1, $source->checkCount, 'Stale cache should have been discarded.' );
        $this->assertSame( '0.2.2', $result->currentVersion );
        $this->assertFalse( $result->hasUpdate() );
    }

    /**
     * Regression for #226: a fresh cache whose `currentVersion` still matches
     * `config('app.version')` is served without re-hitting the source.
     *
     * @since 2.5.3
     */
    public function test_check_for_update_serves_cache_when_current_version_matches(): void
    {
        config( ['app.version' => '0.2.0'] );

        $cacheKey = 'cms.' . UpdateType::Application->value . '.test-app.update_check';

        $cached = new UpdateInfo(
            currentVersion: '0.2.0',
            latestVersion: '0.2.2',
            downloadUrl: 'https://example.com/update.zip',
        );

        Cache::put( $cacheKey, $cached, 3600 );

        $source = new class implements UpdateSourceInterface {
            public int $checkCount = 0;

            public function supports( string $url ): bool
            {
                return true;
            }

            public function checkForUpdate(): UpdateInfo
            {
                $this->checkCount++;

                return new UpdateInfo( '0.0.0', '0.0.0', '' );
            }

            public function downloadUpdate( string $version ): string
            {
                return '/tmp/noop.zip';
            }

            public function setAuthentication( string|array $credentials ): void
            {
            }

            public function getName(): string
            {
                return 'Fake';
            }
        };

        $checker = new UpdateChecker( $source, UpdateType::Application, 'test-app' );

        $result = $checker->checkForUpdate();

        $this->assertSame( 0, $source->checkCount, 'Fresh cache should have been served.' );
        $this->assertSame( '0.2.0', $result->currentVersion );
        $this->assertSame( '0.2.2', $result->latestVersion );
    }

    /**
     * Regression for #226: when the host never sets `app.version`, the
     * cache-staleness check has nothing fresher to compare against and must
     * NOT evict the cache — otherwise the "unset app.version" case triggers a
     * source hit on every read (worse than the pre-fix behavior).
     *
     * @since 2.5.3
     */
    public function test_check_for_update_serves_cache_when_app_version_unset(): void
    {
        config( ['app.version' => null] );

        $cacheKey = 'cms.' . UpdateType::Application->value . '.test-app.update_check';

        $cached = new UpdateInfo(
            currentVersion: '0.2.0',
            latestVersion: '0.2.2',
            downloadUrl: 'https://example.com/update.zip',
        );

        Cache::put( $cacheKey, $cached, 3600 );

        $source = new class implements UpdateSourceInterface {
            public int $checkCount = 0;

            public function supports( string $url ): bool
            {
                return true;
            }

            public function checkForUpdate(): UpdateInfo
            {
                $this->checkCount++;

                return new UpdateInfo( '0.0.0', '0.0.0', '' );
            }

            public function downloadUpdate( string $version ): string
            {
                return '/tmp/noop.zip';
            }

            public function setAuthentication( string|array $credentials ): void
            {
            }

            public function getName(): string
            {
                return 'Fake';
            }
        };

        $checker = new UpdateChecker( $source, UpdateType::Application, 'test-app' );

        $result = $checker->checkForUpdate();

        $this->assertSame( 0, $source->checkCount, 'Missing app.version must not evict cache.' );
        $this->assertSame( '0.2.0', $result->currentVersion );
    }

    /**
     * Define environment setup.
     *
     * @since 2.5.3
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment( $app ): void
    {
        $app['config']->set( 'cms.updates.cache_enabled', true );
        $app['config']->set( 'cms.updates.cache_ttl', 3600 );
    }
}
