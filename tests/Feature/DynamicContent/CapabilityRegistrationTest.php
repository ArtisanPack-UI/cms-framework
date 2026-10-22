<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Providers\DynamicContentServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Users\Models\Permission;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Runs the provider's deferred capability registration. The app is already
 * booted in tests, so the `booted()` callback fires immediately.
 */
function registerDynamicContentCapability(): void
{
    $provider = new DynamicContentServiceProvider( app() );

    ( fn () => $this->deferCapabilityRegistration() )->call( $provider );
}

function dynamicContentCapabilityCount(): int
{
    return Permission::where( 'slug', DynamicContentServiceProvider::CAPABILITY )->count();
}

beforeEach( function (): void {
    Cache::flush();
    Permission::where( 'slug', DynamicContentServiceProvider::CAPABILITY )->delete();
} );

test( 'the capability is seeded', function (): void {
    registerDynamicContentCapability();

    expect( dynamicContentCapabilityCount() )->toBe( 1 );
} );

test( 'once seeded, later boots skip the permission queries', function (): void {
    registerDynamicContentCapability();

    DB::enableQueryLog();
    DB::flushQueryLog();

    registerDynamicContentCapability();

    $permissionQueries = collect( DB::getQueryLog() )
        ->filter( fn ( array $query ): bool => str_contains( $query['query'], 'permissions' ) );

    expect( $permissionQueries )->toBeEmpty();
} );

test( 'a stale flag after a migration run is cleared and the capability re-seeded', function (): void {
    registerDynamicContentCapability();

    // Simulate `migrate:fresh` wiping the row while the cache flag survives.
    Permission::where( 'slug', DynamicContentServiceProvider::CAPABILITY )->delete();

    registerDynamicContentCapability();
    expect( dynamicContentCapabilityCount() )->toBe( 0 );

    event( new MigrationsEnded( 'up' ) );

    expect( dynamicContentCapabilityCount() )->toBe( 1 );
} );

test( 'the flag is scoped to the database so a shared cache store does not skip the seed', function (): void {
    registerDynamicContentCapability();

    Permission::where( 'slug', DynamicContentServiceProvider::CAPABILITY )->delete();

    // Another site sharing this cache store, pointed at its own database.
    $connection = config( 'database.default' );
    config( ['database.connections.' . $connection . '.database' => 'other-site'] );

    registerDynamicContentCapability();

    expect( dynamicContentCapabilityCount() )->toBe( 1 );
} );

test( 'the seed still runs when the cache store is unavailable', function (): void {
    Cache::shouldReceive( 'has' )->andThrow( new RuntimeException( 'cache table missing' ) );
    Cache::shouldReceive( 'forever' )->andThrow( new RuntimeException( 'cache table missing' ) );

    registerDynamicContentCapability();

    expect( dynamicContentCapabilityCount() )->toBe( 1 );
} );
