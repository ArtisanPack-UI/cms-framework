<?php

use ArtisanPackUI\CMSFramework\Features\Plugins\Plugin;
use Illuminate\Support\Str;

// Test that Plugin constructor requires name and slug
it('throws exception when name or slug is not provided', function () {
    // Create a mock of the abstract Plugin class without required properties
    $mockPlugin = new class extends Plugin {
        // No name or slug defined
    };
})->throws(InvalidArgumentException::class, "Plugin must define a 'name' and 'slug' property in its main Plugin.php class.");

// Test that Plugin constructor properly slugifies the slug
it('slugifies the slug property', function () {
    // Create a mock of the abstract Plugin class with a non-slugified slug
    $mockPlugin = new class extends Plugin {
        public string $name = 'Test Plugin';
        public string $slug = 'Test Slug With Spaces';
    };

    // Assert that the slug has been properly slugified
    expect($mockPlugin->slug)->toBe('test-slug-with-spaces');
});

// Test default values for optional properties
it('has default values for optional properties', function () {
    // Create a mock of the abstract Plugin class with only required properties
    $mockPlugin = new class extends Plugin {
        public string $name = 'Test Plugin';
        public string $slug = 'test-plugin';
    };

    // Assert default values
    expect($mockPlugin->version)->toBe('1.0.0');
    expect($mockPlugin->author)->toBe('Unknown');
    expect($mockPlugin->website)->toBeNull();
    expect($mockPlugin->description)->toBeNull();
});

// Test that register method can be called
it('can call register method', function () {
    // Create a mock of the abstract Plugin class with a custom register method
    $mockPlugin = new class extends Plugin {
        public string $name = 'Test Plugin';
        public string $slug = 'test-plugin';
        public bool $registerCalled = false;

        public function register(): void
        {
            $this->registerCalled = true;
        }
    };

    // Call the register method
    $mockPlugin->register();

    // Assert that the register method was called
    expect($mockPlugin->registerCalled)->toBeTrue();
});

// Test that boot method can be called
it('can call boot method', function () {
    // Create a mock of the abstract Plugin class with a custom boot method
    $mockPlugin = new class extends Plugin {
        public string $name = 'Test Plugin';
        public string $slug = 'test-plugin';
        public bool $bootCalled = false;

        public function boot(): void
        {
            $this->bootCalled = true;
        }
    };

    // Call the boot method
    $mockPlugin->boot();

    // Assert that the boot method was called
    expect($mockPlugin->bootCalled)->toBeTrue();
});

// Test registerMigrations method returns empty array by default
it('returns empty array for registerMigrations by default', function () {
    // Create a mock of the abstract Plugin class
    $mockPlugin = new class extends Plugin {
        public string $name = 'Test Plugin';
        public string $slug = 'test-plugin';
    };

    // Assert that registerMigrations returns an empty array by default
    expect($mockPlugin->registerMigrations())->toBeArray();
    expect($mockPlugin->registerMigrations())->toBeEmpty();
});

// Test registerSettings method returns empty array by default
it('returns empty array for registerSettings by default', function () {
    // Create a mock of the abstract Plugin class
    $mockPlugin = new class extends Plugin {
        public string $name = 'Test Plugin';
        public string $slug = 'test-plugin';
    };

    // Assert that registerSettings returns an empty array by default
    expect($mockPlugin->registerSettings())->toBeArray();
    expect($mockPlugin->registerSettings())->toBeEmpty();
});

// Test registerPermissions method returns empty array by default
it('returns empty array for registerPermissions by default', function () {
    // Create a mock of the abstract Plugin class
    $mockPlugin = new class extends Plugin {
        public string $name = 'Test Plugin';
        public string $slug = 'test-plugin';
    };

    // Assert that registerPermissions returns an empty array by default
    expect($mockPlugin->registerPermissions())->toBeArray();
    expect($mockPlugin->registerPermissions())->toBeEmpty();
});

// Test custom migrations can be registered
it('can register custom migrations', function () {
    // Create a mock of the abstract Plugin class with custom migrations
    $mockPlugin = new class extends Plugin {
        public string $name = 'Test Plugin';
        public string $slug = 'test-plugin';

        public function registerMigrations(): array
        {
            return ['database/migrations'];
        }
    };

    // Assert that registerMigrations returns the custom migrations
    expect($mockPlugin->registerMigrations())->toContain('database/migrations');
});

// Test custom settings can be registered
it('can register custom settings', function () {
    // Create a mock of the abstract Plugin class with custom settings
    $mockPlugin = new class extends Plugin {
        public string $name = 'Test Plugin';
        public string $slug = 'test-plugin';

        public function registerSettings(): array
        {
            return [
                [
                    'key' => 'test_plugin.setting',
                    'default' => 'default_value',
                    'type' => 'string',
                    'description' => 'A test setting'
                ]
            ];
        }
    };

    // Assert that registerSettings returns the custom settings
    $settings = $mockPlugin->registerSettings();
    expect($settings)->toHaveCount(1);
    expect($settings[0]['key'])->toBe('test_plugin.setting');
    expect($settings[0]['default'])->toBe('default_value');
    expect($settings[0]['type'])->toBe('string');
    expect($settings[0]['description'])->toBe('A test setting');
});

// Test custom permissions can be registered
it('can register custom permissions', function () {
    // Create a mock of the abstract Plugin class with custom permissions
    $mockPlugin = new class extends Plugin {
        public string $name = 'Test Plugin';
        public string $slug = 'test-plugin';

        public function registerPermissions(): array
        {
            return [
                'test_plugin.permission' => [
                    'label' => 'Test Permission',
                    'description' => 'A test permission'
                ]
            ];
        }
    };

    // Assert that registerPermissions returns the custom permissions
    $permissions = $mockPlugin->registerPermissions();
    expect($permissions)->toHaveCount(1);
    expect($permissions['test_plugin.permission']['label'])->toBe('Test Permission');
    expect($permissions['test_plugin.permission']['description'])->toBe('A test permission');
});
