<?php

/**
 * Unit Tests for the Settings Helper Functions.
 *
 * Verifies the helper functions correctly wrap the SettingsManager.
 *
 * @since      2.0.0
 */

use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;

beforeEach( function (): void {
    // Mock the manager and bind it to the service container
    $this->artisan( 'migrate', ['--database' => 'testing'] );
    $this->managerMock = Mockery::mock( SettingsManager::class );
    $this->app->instance( SettingsManager::class, $this->managerMock );
} );

afterEach( function (): void {
    Mockery::close();
} );

test( 'apGetSetting helper', function (): void {
    $this->managerMock
        ->shouldReceive( 'getSetting' )
        ->with( 'test-key', 'default' )
        ->once()
        ->andReturn( 'expected-value' );

    $value = apGetSetting( 'test-key', 'default' );

    expect( $value )->toBe( 'expected-value' );
} );

test( 'apRegisterSetting helper', function (): void {
    $callback = fn () => 'test';

    $this->managerMock
        ->shouldReceive( 'registerSetting' )
        // --- FIX: Correct argument order ---
        ->with( 'test-key', 'default', $callback, 'string' )
        ->once();

    // The actual call matches the helper and manager signature
    apRegisterSetting( 'test-key', 'default', $callback, 'string' );

    // Mockery assertions are checked automatically in afterEach.
    expect( true )->toBeTrue();
} );

test( 'apUpdateSetting helper', function (): void {
    $this->managerMock
        ->shouldReceive( 'updateSetting' )
        ->with( 'test-key', 'new-value' )
        ->once();

    apUpdateSetting( 'test-key', 'new-value' );

    // Mockery assertions are checked automatically in afterEach.
    expect( true)->toBeTrue();
});
