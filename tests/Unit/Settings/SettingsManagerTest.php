<?php
/**
 * Unit Tests for the SettingsManager.
 *
 * Verifies the registration, retrieval, and update logic.
 *
 * @since      2.0.0
 * @package    ArtisanPackUI\CMSFramework\Modules\Settings\Tests\Unit
 */

use ArtisanPackUI\CMSFramework\Modules\Settings\Managers\SettingsManager;
use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;

// Define properties using Pest's dataset feature or beforeEach
beforeEach( function () {
	$this->artisan( 'migrate', [ '--database' => 'testing' ] );

	$this->manager   = app( SettingsManager::class );
	$this->filterTag = 'ap.settings.registeredSettings';
} );

test( 'it registers setting via filter', function () {
	$this->manager->registerSetting(
		'test-key',
		'default-value',
		fn( $value ) => trim( $value ), // Using a placeholder string for the callable
		'string'
	);

	$settings = applyFilters( $this->filterTag, [] );

	expect( $settings )->toHaveKey( 'test-key' );
	expect( $settings['test-key']['default'] )->toBe( 'default-value' );
	expect( $settings['test-key']['type'] )->toBe( 'string' );
} );

test( 'it gets setting from database', function () {
	Setting::create( [ 'key' => 'db-key', 'value' => 'db-value' ] );

	$value = $this->manager->getSetting( 'db-key', 'default' );

	expect( $value )->toBe( 'db-value' );
} );

test( 'it gets registered default setting', function () {
	$this->manager->registerSetting( 'reg-key', 'reg-default', 'trim', 'string' );

	$value = $this->manager->getSetting( 'reg-key' );

	expect( $value )->toBe( 'reg-default' );
} );

test( 'it gets user provided default setting', function () {
	$this->manager->registerSetting( 'reg-key', 'reg-default', 'trim', 'string' );

	// The user-provided default takes precedence (based on current logic).
	$value = $this->manager->getSetting( 'reg-key', 'user-default' );

	expect( $value )->toBe( 'user-default' );
} );

test( 'it returns null for non existent setting', function () {
	$value = $this->manager->getSetting( 'non-existent-key' );
	expect( $value )->toBeNull();
} );

test( 'it updates setting and creates new', function () {
	$this->manager->registerSetting(
		'new-key',
		'',
		fn( $value ) => trim( $value ), // Use a real callable
		'string'
	);

	$this->manager->updateSetting( 'new-key', '  new-value  ' );

	$this->assertDatabaseHas( 'settings', [
		'key'   => 'new-key',
		'value' => 'new-value', // Value should be trimmed
		'type'  => 'string',
	] );
} );

test( 'it updates setting and updates existing', function () {
	Setting::create( [ 'key' => 'existing-key', 'value' => 'old-value', 'type' => 'string' ] );

	$this->manager->registerSetting(
		'existing-key',
		'',
		fn( $value ) => (string) ( (int) $value * 2 ), // Dummy sanitization
		'string'
	);

	$this->manager->updateSetting( 'existing-key', '100' );

	$this->assertDatabaseHas( 'settings', [
		'key'   => 'existing-key',
		'value' => '200', // Value should be "sanitized"
	] );
} );