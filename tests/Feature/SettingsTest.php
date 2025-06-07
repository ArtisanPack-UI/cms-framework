<?php

use ArtisanPackUI\CMSFramework\Models\Setting;

it( 'can register settings', function () {
    cmsFramework()->settingsRegisterSetting( 'test-setting', 'test-value', 'sanitizeText' );

    $this->assertEquals( 'test-value', cmsFramework()->settingsGetSetting( 'test-setting' ) );
} );

it( 'can update settings', function () {
    cmsFramework()->settingsRegisterSetting( 'test-setting', 'test-value', 'sanitizeText' );
    $this->assertNotFalse( cmsFramework()->settingsUpdateSetting( 'test-setting', 'test-value-2' ) );
    $this->assertEquals( 'test-value-2', cmsFramework()->settingsGetSetting( 'test-setting' ) );
} );

it( 'can delete settings', function () {
    cmsFramework()->settingsRegisterSetting( 'test-setting', 'test-value', 'sanitizeText' );
    cmsFramework()->settingsDeleteSetting( 'test-setting' );
    $this->assertEquals( false, cmsFramework()->settingsGetSetting( 'test-setting' ) );
} );

it( 'can get all settings', function () {
    // Create multiple settings
    cmsFramework()->settingsRegisterSetting( 'test-setting-1', 'test-value-1', 'sanitizeText' );
    cmsFramework()->settingsRegisterSetting( 'test-setting-2', 'test-value-2', 'sanitizeText' );

    // Get all settings
    $settings = cmsFramework()->settingsGetSettings();

    // Assert that the settings array contains the expected settings
    $this->assertIsArray( $settings );
    $this->assertGreaterThanOrEqual( 2, count( $settings ) );
} );

// Test for getSettings method with category filter
it( 'can get settings by category', function () {
    // Create settings with different categories
    cmsFramework()->settingsRegisterSetting( 'test-setting-1', 'test-value-1', 'sanitizeText', 'category1' );
    cmsFramework()->settingsRegisterSetting( 'test-setting-2', 'test-value-2', 'sanitizeText', 'category2' );

    // Get settings by category
    $settings = cmsFramework()->settingsGetSettings( [ 'category' => 'category1' ] );

    // Assert that only settings from the specified category are returned
    $this->assertIsArray( $settings );
    $this->assertCount( 1, $settings );
    $this->assertEquals( 'category1', $settings[0]['category'] );
} );

// Test for getSetting method with default value
it( 'returns default value when setting does not exist', function () {
    // Try to get a non-existent setting with a default value
    $value = cmsFramework()->settingsGetSetting( 'non-existent-setting', 'default-value' );

    // Assert that the default value is returned
    $this->assertEquals( 'default-value', $value );
} );

// Test for addSetting method directly
it( 'can add setting directly', function () {
    // Add a setting directly
    cmsFramework()->settingsAddSetting( 'direct-setting', 'direct-value', 'direct-category' );

    // Verify the setting was added
    $value = cmsFramework()->settingsGetSetting( 'direct-setting' );
    $this->assertEquals( 'direct-value', $value );

    // Verify the category was set correctly
    $setting = Setting::where( 'name', 'direct-setting' )->first();
    $this->assertEquals( 'direct-category', $setting->category );
} );

// Test for updating a non-existent setting
it( 'returns false when updating non-existent setting', function () {
    // Try to update a non-existent setting
    $result = cmsFramework()->settingsUpdateSetting( 'non-existent-setting', 'new-value' );

    // Assert that the update operation returns false
    $this->assertFalse( $result );
} );

// Test for deleting a non-existent setting
it( 'returns false when deleting non-existent setting', function () {
    // Try to delete a non-existent setting
    $result = cmsFramework()->settingsDeleteSetting( 'non-existent-setting' );

    // Assert that the delete operation returns false
    $this->assertFalse( $result );
} );

// Test for registering a setting with the same name
it( 'does not duplicate settings when registering with the same name', function () {
    // Register a setting
    cmsFramework()->settingsRegisterSetting( 'duplicate-setting', 'original-value', 'sanitizeText' );

    // Register another setting with the same name but different value
    cmsFramework()->settingsRegisterSetting( 'duplicate-setting', 'new-value', 'sanitizeText' );

    // Get the setting value
    $value = cmsFramework()->settingsGetSetting( 'duplicate-setting' );

    // Assert that the original value is preserved
    $this->assertEquals( 'original-value', $value );

    // Count the number of settings with this name
    $count = Setting::where( 'name', 'duplicate-setting' )->count();

    // Assert that only one setting exists with this name
    $this->assertEquals( 1, $count );
} );

// Test for the sanitization callback
it( 'sanitizes values using the provided callback', function () {
    // Define a custom sanitization callback
    $sanitizeCallback = function ( $value ) {
        return strtoupper( $value );
    };

    // Register a setting with the custom callback
    cmsFramework()->settingsRegisterSetting( 'sanitize-test', 'test-value', $sanitizeCallback );

    // Update the setting
    cmsFramework()->settingsUpdateSetting( 'sanitize-test', 'new-value' );

    // Get the setting value
    $value = cmsFramework()->settingsGetSetting( 'sanitize-test' );

    // Assert that the value was sanitized (converted to uppercase)
    $this->assertEquals( 'NEW-VALUE', $value );
} );
