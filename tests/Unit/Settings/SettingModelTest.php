<?php

/**
 * Unit Tests for the Setting Model.
 *
 * Verifies the custom casting attribute for the 'value' field.
 *
 * @since      2.0.0
 */

use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );
} );

test( 'it casts string value', function (): void {
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

test( 'it casts integer value', function (): void {
    $setting = Setting::create( [
        'key'   => 'test-int',
        'value' => 123,
    ] );

    // Check database directly *before* refresh for debugging
    $this->assertDatabaseHas( 'settings', [
        'key'   => 'test-int',
        'value' => '123',
        'type'  => 'integer', // <-- Verify type was saved
    ] );

    $setting->refresh(); // Refresh should now load the correct type

    expect( $setting->type )->toBe( 'integer' ); // <-- This should now pass
    expect( $setting->value )->toBe( 123 );
} );

test( 'it casts boolean true value', function (): void {
    $setting = Setting::create( [
        'key'   => 'test-bool-true',
        'value' => true,
    ] );

    $this->assertDatabaseHas( 'settings', [
        'key'   => 'test-bool-true',
        'value' => '1',
        'type'  => 'boolean', // <-- Verify type was saved
    ] );

    $setting->refresh();

    expect( $setting->type )->toBe( 'boolean' );
    expect( $setting->value )->toBeTrue();
} );

test( 'it casts boolean false value', function (): void {
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

test( 'it casts array value', function (): void {
    $array   = ['a' => 1, 'b' => 2];
    $setting = Setting::create( [
        'key'   => 'test-array',
        'value' => $array,
    ] );

    $this->assertDatabaseHas( 'settings', [
        'key'   => 'test-array',
        'value' => json_encode( $array ),
        'type'  => 'json', // <-- Verify type was saved
    ] );

    $setting->refresh();

    expect( $setting->type )->toBe( 'json' ); // <-- Should pass
    expect( $setting->value )->toBe( $array );
} );

test( 'it handles null value', function (): void {
    // Test case 1: Using the setter with null
    $setting = Setting::create( [
        'key'   => 'test-null-via-setter',
        'value' => null,
    ] );
    $setting->refresh();
    expect( $setting->type )->toBe( 'string' );
    expect( $setting->value )->toBe( '' );                                                                                      // Setter stores null string as ''
    $this->assertDatabaseHas( 'settings', ['key' => 'test-null-via-setter', 'value' => '', 'type' => 'string'] );

    // Test case 2: Simulating a raw null value from DB for a 'string' type
    $setting = Setting::create( ['key' => 'test-raw-null-string', 'value' => 'initial', 'type' => 'string'] );                // Create with initial value
    Illuminate\Support\Facades\DB::table( 'settings' )->where( 'key', 'test-raw-null-string' )->update( ['value' => null] ); // Force DB null
    $setting->refresh();                                                                                                        // Reload

    expect( $setting->type )->toBe( 'string' );
    // --- FIX: Expect empty string because type is string ---
    expect( $setting->value )->toBe( '' );                                                                                      // Getter returns '' for null string type

    // Test case 3: Simulating a raw null value from DB for a non-string type
    $setting = Setting::create( ['key' => 'test-raw-null-int', 'value' => 123, 'type' => 'integer'] );                        // Create with initial value
    Illuminate\Support\Facades\DB::table( 'settings' )->where( 'key', 'test-raw-null-int' )->update( ['value' => null]);    // Force DB null
    $setting->refresh();                                                                                                        // Reload

    expect( $setting->type)->toBe( 'integer');
    expect( $setting->value)->toBeNull(); // Getter returns null for null non-string type
});
