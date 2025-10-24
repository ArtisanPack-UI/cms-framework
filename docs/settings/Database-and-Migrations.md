---
title: Database and Migrations
---

# Database and Migrations

The Settings module persists values in a dedicated `settings` database table. This guide explains the schema and migration considerations.

## Running the Migration

Run your application's migrations (this package provides the migration):

```bash
php artisan migrate
```

This will create the `settings` table if it does not already exist.

## Table Schema

The table structure is simple and optimized for key–value storage:

```php
Schema::create('settings', function (Blueprint $table) {
    $table->string('key')->primary(); // Unique key for each setting
    $table->text('value')->nullable(); // Stored representation of the value
    $table->string('type')->default('string'); // Type hint for casting
    $table->timestamps(); // created_at, updated_at
});
```

- key: Primary key string identifier (e.g., `site.title`, `site.is_private`).
- value: Text column that stores the normalized value (even for non-string types).
- type: Indicates how the value should be cast on retrieval (`string`, `boolean`, `integer`, `float`, `json`).

## Casting and Model Behavior

The `ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting` model:
- Automatically determines `type` when saving based on the PHP value you pass.
- Normalizes `value` for storage.
- Casts the stored value back to the appropriate PHP type on read.

## Safe Reads Before Migration

The SettingsManager checks `Schema::hasTable('settings')` before querying. If the table does not exist yet, reads will fall back to:
1) The explicit default you pass to `getSetting($key, $default)`
2) Otherwise the registered default for the key

This allows you to safely call `apGetSetting()` during early boot even before migrations run.

## Backing Up and Seeding

- Backup: Include the `settings` table in your backup strategy.
- Seeding: You can pre-populate common settings in a database seeder using `apUpdateSetting()` after registration.

## Deleting Settings

To remove a setting record entirely:

```php
use ArtisanPackUI\CMSFramework\Modules\Settings\Models\Setting;

Setting::where('key', 'site.title')->delete();
```

Note: If you delete a record, reads will return the registered default or your explicit fallback.
