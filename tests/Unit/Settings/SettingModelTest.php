<?php
/**
 * Unit Tests for the Setting Model.
 *
 * Verifies the custom casting attribute for the 'value' field.
 *
 * @since      2.0.0
 * @package    ArtisanPackUI\CMSFramework\Modules\Settings\Tests\Unit
 */

use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;

beforeEach( function () {
	$this->artisan( 'migrate', [ '--database' => 'testing' ] );
} );

test( 'it casts string value', function () {
	$setting = Setting::create( [
									'key'   => 'test-string',
									'value' => 'Hello World',
								] );

	$setting->refresh();

	expect( $setting->type )->toBe( 'string' );
	expect( $setting->value )->toBe( 'Hello World' );
	$this->assertDatabaseHas( 'settings', [
		'key'   => 'test-string',
		'value' => 'Hello World',
		'type'  => 'string',
	] );
} );

test( 'it casts integer value', function () {
	$setting = Setting::create( [
									'key'   => 'test-int',
									'value' => 123,
								] );

	$setting->refresh();

	expect( $setting->type )->toBe( 'integer' );
	expect( $setting->value )->toBe( 123 );
	$this->assertDatabaseHas( 'settings', [
		'key'   => 'test-int',
		'value' => '123',
		'type'  => 'integer',
	] );
} );

test( 'it casts boolean true value', function () {
	$setting = Setting::create( [
									'key'   => 'test-bool-true',
									'value' => true,
								] );

	$setting->refresh();

	expect( $setting->type )->toBe( 'boolean' );
	expect( $setting->value )->toBeTrue();
	$this->assertDatabaseHas( 'settings', [
		'key'   => 'test-bool-true',
		'value' => '1',
		'type'  => 'boolean',
	] );
} );

test( 'it casts boolean false value', function () {
	$setting = Setting::create( [
									'key'   => 'test-bool-false',
									'value' => false,
								] );

	$setting->refresh();

	expect( $setting->type )->toBe( 'boolean' );
	expect( $setting->value )->toBeFalse();
	$this->assertDatabaseHas( 'settings', [
		'key'   => 'test-bool-false',
		'value' => '0',
		'type'  => 'boolean',
	] );
} );

test( 'it casts array value', function () {
	$array   = [ 'a' => 1, 'b' => 2 ];
	$setting = Setting::create( [
									'key'   => 'test-array',
									'value' => $array,
								] );

	$setting->refresh();

	expect( $setting->type )->toBe( 'json' );
	expect( $setting->value )->toBe( $array );
	$this->assertDatabaseHas( 'settings', [
		'key'   => 'test-array',
		'value' => json_encode( $array ),
		'type'  => 'json',
	] );
} );

test( 'it handles null value', function () {
	$setting = Setting::create( [
									'key'   => 'test-null',
									'value' => null,
								] );

	$setting->refresh();

	// The mutator casts null to an empty string.
	expect( $setting->type )->toBe( 'string' );
	expect( $setting->value )->toBe( '' );
	$this->assertDatabaseHas( 'settings', [
		'key'   => 'test-null',
		'value' => '',
		'type'  => 'string',
	] );

	// Test retrieval of a raw null value
	$setting->type = 'string';
	$setting->setRawAttributes( [ 'value' => null ], true );
	expect( $setting->value )->toBeNull();
} );