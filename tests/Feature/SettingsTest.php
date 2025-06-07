<?php

use ArtisanPackUI\CMSFramework\Models\Setting;
use ArtisanPackUI\CMSFramework\Features\Settings\SettingsManager;

it('can register settings', function () {
    $settingsManager = app(SettingsManager::class);
    $settingsManager->register('test-setting', 'test-value', 'string', 'Test setting description');

    $this->assertEquals('test-value', $settingsManager->get('test-setting'));
});

it('can set settings', function () {
    $settingsManager = app(SettingsManager::class);
    $settingsManager->register('test-setting', 'test-value', 'string');
    $setting = $settingsManager->set('test-setting', 'test-value-2');

    $this->assertInstanceOf(Setting::class, $setting);
    $this->assertEquals('test-value-2', $settingsManager->get('test-setting'));
});

it('can delete settings', function () {
    $settingsManager = app(SettingsManager::class);
    $settingsManager->register('test-setting', 'test-value', 'string');
    $result = $settingsManager->delete('test-setting');

    $this->assertTrue($result);
    $this->assertNull($settingsManager->get('test-setting'));
});

it('can get all settings', function () {
    $settingsManager = app(SettingsManager::class);
    // Create multiple settings
    $settingsManager->register('test-setting-1', 'test-value-1', 'string');
    $settingsManager->register('test-setting-2', 'test-value-2', 'string');

    // Get all settings
    $settings = $settingsManager->all();

    // Assert that the settings array contains the expected settings
    $this->assertIsArray($settings);
    $this->assertArrayHasKey('test-setting-1', $settings);
    $this->assertArrayHasKey('test-setting-2', $settings);
});

// Test for get method with default value
it('returns default value when setting does not exist', function () {
    $settingsManager = app(SettingsManager::class);

    // Try to get a non-existent setting with a default value
    $value = $settingsManager->get('non-existent-setting', 'default-value');

    // Assert that the default value is returned
    $this->assertEquals('default-value', $value);
});

// Test for set method directly
it('can set setting with explicit type', function () {
    $settingsManager = app(SettingsManager::class);

    // Add a setting directly with explicit type
    $setting = $settingsManager->set('direct-setting', 'direct-value', 'string');

    // Verify the setting was added
    $value = $settingsManager->get('direct-setting');
    $this->assertEquals('direct-value', $value);

    // Verify the type was set correctly
    $this->assertEquals('string', $setting->type);
});

// Test for setting boolean values
it('can set and retrieve boolean values', function () {
    $settingsManager = app(SettingsManager::class);

    $settingsManager->set('boolean-setting', true);

    $value = $settingsManager->get('boolean-setting');
    $this->assertIsBool($value);
    $this->assertTrue($value);

    $setting = Setting::where('key', 'boolean-setting')->first();
    $this->assertEquals('boolean', $setting->type);
});

// Test for setting integer values
it('can set and retrieve integer values', function () {
    $settingsManager = app(SettingsManager::class);

    $settingsManager->set('integer-setting', 42);

    $value = $settingsManager->get('integer-setting');
    $this->assertIsInt($value);
    $this->assertEquals(42, $value);

    $setting = Setting::where('key', 'integer-setting')->first();
    $this->assertEquals('integer', $setting->type);
});

// Test for setting array/json values
it('can set and retrieve array values as json', function () {
    $settingsManager = app(SettingsManager::class);

    $arrayValue = ['key1' => 'value1', 'key2' => 'value2'];
    $settingsManager->set('json-setting', $arrayValue);

    $value = $settingsManager->get('json-setting');
    $this->assertIsArray($value);
    $this->assertEquals($arrayValue, $value);

    $setting = Setting::where('key', 'json-setting')->first();
    $this->assertEquals('json', $setting->type);
});

// Test for registering a setting with the same key
it('does not duplicate settings when registering with the same key', function () {
    $settingsManager = app(SettingsManager::class);

    // Register a setting
    $settingsManager->register('duplicate-setting', 'original-value');

    // Register another setting with the same key but different value
    $settingsManager->register('duplicate-setting', 'new-value');

    // Get the setting value
    $value = $settingsManager->get('duplicate-setting');

    // Assert that the original value is preserved
    $this->assertEquals('original-value', $value);

    // Count the number of settings with this key
    $count = Setting::where('key', 'duplicate-setting')->count();

    // Assert that only one setting exists with this key
    $this->assertEquals(1, $count);
});

// Test for refreshing settings cache
it('refreshes settings cache when updating settings', function () {
    $settingsManager = app(SettingsManager::class);

    // Register a setting
    $settingsManager->register('cache-test', 'original-value');

    // Verify the original value
    $this->assertEquals('original-value', $settingsManager->get('cache-test'));

    // Update the setting directly in the database to bypass the manager
    $setting = Setting::where('key', 'cache-test')->first();
    $setting->value = 'updated-value';
    $setting->save();

    // Value should still be the cached one
    $this->assertEquals('original-value', $settingsManager->get('cache-test'));

    // Refresh the cache
    $settingsManager->refreshSettingsCache();

    // Now the value should be updated
    $this->assertEquals('updated-value', $settingsManager->get('cache-test'));
});
