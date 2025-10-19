<?php
/**
 * Unit Tests for the Settings Helper Functions.
 *
 * Verifies the helper functions correctly wrap the SettingsManager.
 *
 * @since      2.0.0
 * @package    ArtisanPackUI\CMSFramework\Modules\Settings\Tests\Unit
 */

use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use Mockery;

beforeEach( function () {
	// Mock the manager and bind it to the service container
	$this->artisan( 'migrate', [ '--database' => 'testing' ] );
	$this->managerMock = Mockery::mock( SettingsManager::class );
	$this->app->instance( SettingsManager::class, $this->managerMock );
} );

afterEach( function () {
	Mockery::close();
} );

test( 'apGetSetting helper', function () {
	$this->managerMock
		->shouldReceive( 'getSetting' )
		->with( 'test-key', 'default' )
		->once()
		->andReturn( 'expected-value' );

	$value = apGetSetting( 'test-key', 'default' );

	expect( $value )->toBe( 'expected-value' );
} );

test( 'apRegisterSetting helper', function () {
	$callback = fn() => 'test';

	$this->managerMock
		->shouldReceive( 'registerSetting' )
		->with( 'test-key', 'default', 'string', $callback )
		->once();

	apRegisterSetting( 'test-key', 'default', $callback, 'string' );

	// Mockery assertions are checked automatically in afterEach.
	expect( true )->toBeTrue();
} );

test( 'apUpdateSetting helper', function () {
	$this->managerMock
		->shouldReceive( 'updateSetting' )
		->with( 'test-key', 'new-value' )
		->once();

	apUpdateSetting( 'test-key', 'new-value' );

	// Mockery assertions are checked automatically in afterEach.
	expect( true )->toBeTrue();
} );