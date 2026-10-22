<?php

declare( strict_types=1 );

/**
 * Unit Tests for the SettingsManager.
 *
 * Verifies the registration, retrieval, and update logic.
 *
 * @since      2.0.0
 */

use ArtisanPackUI\CMSFramework\Modules\Settings\Enums\SettingType;
use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Define properties using Pest's dataset feature or beforeEach
beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );

    $this->manager   = app( SettingsManager::class );
    $this->filterTag = 'ap.settings.registeredSettings';
} );

test( 'it registers setting via filter', function (): void {
    $this->manager->registerSetting(
        'test-key',
        'default-value',
        fn ( $value ) => trim( $value ), // Using a placeholder string for the callable
        SettingType::String,
    );

    $settings = applyFilters( $this->filterTag, [] );

    expect( $settings )->toHaveKey( 'test-key' );
    expect( $settings['test-key']['default'] )->toBe( 'default-value' );
    expect( $settings['test-key']['type'] )->toBe( SettingType::String );
} );

test( 'it gets setting from database', function (): void {
    Setting::create( ['key' => 'db-key', 'value' => 'db-value'] );

    $value = $this->manager->getSetting( 'db-key', 'default' );

    expect( $value )->toBe( 'db-value' );
} );

test( 'it gets registered default setting', function (): void {
    $this->manager->registerSetting( 'reg-key', 'reg-default', 'trim', SettingType::String );

    $value = $this->manager->getSetting( 'reg-key' );

    expect( $value )->toBe( 'reg-default' );
} );

test( 'it gets user provided default setting', function (): void {
    $this->manager->registerSetting( 'reg-key', 'reg-default', 'trim', SettingType::String );

    // The user-provided default takes precedence (based on current logic).
    $value = $this->manager->getSetting( 'reg-key', 'user-default' );

    expect( $value )->toBe( 'user-default' );
} );

test( 'it returns null for non existent setting', function (): void {
    $value = $this->manager->getSetting( 'non-existent-key' );
    expect( $value )->toBeNull();
} );

test( 'it updates setting and creates new', function (): void {
    $this->manager->registerSetting(
        'new-key',
        '',
        fn ( $value ) => trim( $value ), // Use a real callable
        SettingType::String,
    );

    $this->manager->updateSetting( 'new-key', '  new-value  ' );

    $this->assertDatabaseHas( 'settings', [
        'key'   => 'new-key',
        'value' => 'new-value', // Value should be trimmed
        'type'  => 'string',
    ] );
} );

test( 'it updates setting and updates existing', function (): void {
    Setting::create( ['key' => 'existing-key', 'value' => 'old-value', 'type' => 'string'] );

    $this->manager->registerSetting(
        'existing-key',
        '',
        fn ( $value ) => (string) ( (int) $value * 2 ), // Dummy sanitization
        SettingType::String,
    );

    $this->manager->updateSetting( 'existing-key', '100' );

    $this->assertDatabaseHas( 'settings', [
        'key'   => 'existing-key',
        'value' => '200', // Value should be "sanitized"
    ] );
} );

/**
 * Counts the queries executed while running the given callback.
 *
 * @param  callable  $callback  Code to measure.
 *
 * @return int Number of queries executed.
 */
function countSettingsQueries( callable $callback ): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $callback();

    $count = count( DB::getQueryLog() );

    DB::disableQueryLog();

    return $count;
}

test( 'it reads all settings with at most one query per request', function (): void {
    Setting::create( ['key' => 'a', 'value' => 'one'] );
    Setting::create( ['key' => 'b', 'value' => 'two'] );

    // The positive table-existence check is remembered for the process, so
    // warm it first and measure a cold cache in a request that follows.
    $this->manager->getSetting( 'a' );
    $this->manager->flushCache();

    $queries = countSettingsQueries( function (): void {
        for ( $i = 0; $i < 50; $i++ ) {
            expect( $this->manager->getSetting( 'a' ) )->toBe( 'one' );
            expect( $this->manager->getSetting( 'b' ) )->toBe( 'two' );
            expect( $this->manager->getSetting( 'missing', 'fallback' ) )->toBe( 'fallback' );
        }
    } );

    expect( $queries )->toBe( 1 );
} );

test( 'it serves settings from the shared cache without querying', function (): void {
    Setting::create( ['key' => 'cached-key', 'value' => 'cached-value'] );
    $this->manager->getSetting( 'cached-key' );
    $this->manager->forgetLoadedSettings();

    $queries = countSettingsQueries( fn () => expect( $this->manager->getSetting( 'cached-key' ) )->toBe( 'cached-value' ) );

    expect( $queries )->toBe( 0 );
} );

test( 'it expires stale cached rows after the cache TTL', function (): void {
    Setting::create( ['key' => 'ttl-key', 'value' => 'fresh'] );
    Cache::put( SettingsManager::CACHE_KEY, [['key' => 'ttl-key', 'value' => 'stale', 'type' => 'string']], SettingsManager::CACHE_TTL );

    expect( $this->manager->getSetting( 'ttl-key' ) )->toBe( 'stale' );

    $this->travel( SettingsManager::CACHE_TTL + 1 )->seconds();
    $this->manager->forgetLoadedSettings();

    expect( $this->manager->getSetting( 'ttl-key' ) )->toBe( 'fresh' );
} );

test( 'it preserves value casting for cached settings', function (): void {
    Setting::create( ['key' => 'int-key', 'value' => 42] );
    Setting::create( ['key' => 'bool-key', 'value' => true] );
    Setting::create( ['key' => 'json-key', 'value' => ['nested' => ['x' => 1]]] );
    $this->manager->getSetting( 'int-key' );
    $this->manager->forgetLoadedSettings();

    expect( $this->manager->getSetting( 'int-key' ) )->toBe( 42 )
        ->and( $this->manager->getSetting( 'bool-key' ) )->toBeTrue()
        ->and( $this->manager->getSetting( 'json-key' ) )->toBe( ['nested' => ['x' => 1]] );
} );

test( 'it sees new values after updateSetting', function (): void {
    $this->manager->registerSetting( 'live-key', 'default', 'trim', SettingType::String );

    expect( $this->manager->getSetting( 'live-key' ) )->toBe( 'default' );

    $this->manager->updateSetting( 'live-key', 'first' );
    expect( $this->manager->getSetting( 'live-key' ) )->toBe( 'first' );

    $this->manager->updateSetting( 'live-key', 'second' );
    expect( $this->manager->getSetting( 'live-key' ) )->toBe( 'second' );
} );

test( 'it invalidates the cache on direct model writes and deletes', function (): void {
    Setting::create( ['key' => 'model-key', 'value' => 'original'] );
    expect( $this->manager->getSetting( 'model-key' ) )->toBe( 'original' );

    Setting::findOrFail( 'model-key' )->update( ['value' => 'changed'] );
    expect( $this->manager->getSetting( 'model-key' ) )->toBe( 'changed' );
    expect( Cache::has( SettingsManager::CACHE_KEY ) )->toBeTrue();

    Setting::findOrFail( 'model-key' )->delete();
    expect( Cache::has( SettingsManager::CACHE_KEY ) )->toBeFalse();
    expect( $this->manager->getSetting( 'model-key', 'gone' ) )->toBe( 'gone' );
} );

test( 'it does not write uncommitted values to the shared cache', function (): void {
    Setting::create( ['key' => 'tx-key', 'value' => 'committed'] );
    Cache::forget( SettingsManager::CACHE_KEY );

    try {
        DB::transaction( function (): void {
            Setting::findOrFail( 'tx-key' )->update( ['value' => 'uncommitted'] );

            expect( $this->manager->getSetting( 'tx-key' ) )->toBe( 'uncommitted' );
            expect( Cache::has( SettingsManager::CACHE_KEY ) )->toBeFalse();

            throw new RuntimeException( 'rollback' );
        } );
    } catch ( RuntimeException ) {
    }

    expect( $this->manager->getSetting( 'tx-key' ) )->toBe( 'committed' );
} );

test( 'it clears the shared cache again once the transaction commits', function (): void {
    Setting::create( ['key' => 'commit-key', 'value' => 'old'] );

    DB::transaction( function (): void {
        Setting::findOrFail( 'commit-key' )->update( ['value' => 'new'] );

        // Simulate another process re-caching the committed (old) rows
        // between the write and the commit.
        Cache::put( SettingsManager::CACHE_KEY, [['key' => 'commit-key', 'value' => 'old', 'type' => 'string']], SettingsManager::CACHE_TTL );
    } );

    expect( Cache::has( SettingsManager::CACHE_KEY ) )->toBeFalse();
    expect( $this->manager->getSetting( 'commit-key' ) )->toBe( 'new' );
} );

test( 'it drops the request memo when a queue job starts', function (): void {
    Setting::create( ['key' => 'job-key', 'value' => 'before'] );
    expect( $this->manager->getSetting( 'job-key' ) )->toBe( 'before' );

    DB::table( 'settings' )->where( 'key', 'job-key' )->update( ['value' => 'after'] );
    Cache::forget( SettingsManager::CACHE_KEY );
    expect( $this->manager->getSetting( 'job-key' ) )->toBe( 'before' );

    $job = Mockery::mock( Job::class );
    $job->shouldReceive( 'payload' )->andReturn( [] );

    event( new JobProcessing( 'sync', $job ) );

    expect( $this->manager->getSetting( 'job-key' ) )->toBe( 'after' );
} );

test( 'it does not remember a missing settings table', function (): void {
    Schema::drop( 'settings' );
    $this->manager->flushCache();
    $manager = new SettingsManager;

    expect( $manager->getSetting( 'late-key', 'fallback' ) )->toBe( 'fallback' );

    ( require __DIR__ . '/../../../database/migrations/2025_10_19_210956_create_settings_table.php' )->up();
    Setting::create( ['key' => 'late-key', 'value' => 'stored'] );
    $manager->forgetLoadedSettings();

    expect( $manager->getSetting( 'late-key' ) )->toBe( 'stored' );
} );
