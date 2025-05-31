# Settings Module

The Settings module provides functionality for managing application settings in the ArtisanPack UI CMS Framework.

## Overview

The Settings module allows you to register, add, update, and delete settings in the application. Settings are stored in the database and can be categorized for better organization.

## Classes

### Settings Class

The `Settings` class is the main class of the Settings module. It implements the `Module` interface and provides methods for managing settings.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Settings;
```

#### Methods

##### getSlug(): string
Returns the slug for the module, which is "settings".

**@since** 1.0.0

**@return** string The slug for the module.

##### functions(): array
Returns an array of functions that the module provides. Currently, it provides the following functions:
- `registerSetting`: Registers a setting with the framework
- `addSetting`: Adds a setting to the database
- `getSetting`: Gets a setting from the database
- `getSettings`: Gets all settings from the database
- `updateSetting`: Updates a setting in the database
- `deleteSetting`: Deletes a setting from the database

**@since** 1.0.0

**@return** array List of functions to register.

##### init(): void
Initializes the module by registering the settings migrations.

**@since** 1.0.0

##### registerSetting(string $name, string $value, callable $callback, string $category): void
Registers a setting with the framework. The callback is used to validate and sanitize the setting value.

**@since** 1.0.0

**@param** string $name The name of the setting.
**@param** string $value The value of the setting.
**@param** callable $callback The callback to use for the setting.
**@param** string $category The category for the setting.

##### addSetting(string $setting, string $value, string $category): void
Adds a setting to the database.

**@since** 1.0.0

**@param** string $setting The name of the setting to add.
**@param** string $value The value of the setting to add.
**@param** string $category The category for the setting.

##### getSettings(array $args): array
Gets all settings from the database that match the provided arguments.

**@since** 1.0.0

**@param** array $args The arguments to filter the settings by.
**@return** array The list of settings.

##### getSetting(string $setting, string $default): string
Gets a setting from the database. If the setting doesn't exist, returns the default value.

**@since** 1.0.0

**@param** string $setting The name of the setting to retrieve.
**@param** string $default The default value to return if the setting is not found.
**@return** string The value of the setting, or the default value if not found.

##### updateSetting(string $setting, string $value): Setting|bool
Updates a setting in the database. Returns the updated setting or false if the setting doesn't exist.

**@since** 1.0.0

**@param** string $setting The name of the setting to update.
**@param** string $value The new value for the setting.
**@return** Setting|bool The updated setting, or false if the setting is not found.

##### deleteSetting(string $setting): bool|int
Deletes a setting from the database. Returns true if the setting was deleted, or the number of rows deleted if the setting was not found.

**@since** 1.0.0

**@param** string $setting The name of the setting to delete.
**@return** bool|int True if the setting was deleted, or the number of rows deleted if the setting was not found.

##### settingsMigrations(array $directories): array
Adds custom migration directories for settings.

**@since** 1.0.0

**@param** array $directories The array of migration directories.
**@return** array The array of migration directories.

### Setting Model

The `Setting` model represents a setting in the database.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Settings\Models;
```

#### Properties

- `$guarded`: Array of attributes that are not mass assignable. Only 'id' is guarded.

### SettingFactory

The `SettingFactory` class is used to create Setting models for testing.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Settings\Factories;
```

#### Methods

##### definition(): array
Returns the definition for creating a Setting model.

## Database Schema

The Settings module creates a `settings` table in the database with the following columns:

- `id`: Auto-incrementing primary key
- `key`: String column for the setting name
- `value`: Text column for the setting value (nullable)
- `category`: String column for categorizing settings (nullable)
- `created_at`: Timestamp for when the setting was created
- `updated_at`: Timestamp for when the setting was last updated

## Usage

### Registering a Setting

```php
$settings = new Settings();
$settings->registerSetting('site_name', 'My Site', function($value) {
    return $value;
}, 'general');
```

### Adding a Setting

```php
$settings = new Settings();
$settings->addSetting('site_name', 'My Site', 'general');
```

### Getting a Setting

```php
$settings = new Settings();
$siteName = $settings->getSetting('site_name', 'Default Site Name');
```

### Updating a Setting

```php
$settings = new Settings();
$settings->updateSetting('site_name', 'New Site Name');
```

### Deleting a Setting

```php
$settings = new Settings();
$settings->deleteSetting('site_name');
```

## Hooks

The Settings module provides the following hooks:

### Filters

- `ap.settings.settings`: Allows modification of the settings array
- `ap.migrations.directories`: Allows addition of migration directories
