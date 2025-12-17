<?php

declare(strict_types=1);

use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\UpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->pluginManager = app(PluginManager::class);
    $this->updateManager = new UpdateManager($this->pluginManager);
});

describe('Update Checking', function (): void {
    it('checks for updates for all plugins', function (): void {
        // Create plugins with update URLs
        Plugin::create([
            'slug' => 'test-plugin-1',
            'name' => 'Test Plugin 1',
            'version' => '1.0.0',
            'meta' => [
                'update_url' => 'https://example.com/updates/test-plugin-1',
            ],
        ]);

        Plugin::create([
            'slug' => 'test-plugin-2',
            'name' => 'Test Plugin 2',
            'version' => '1.0.0',
            'meta' => [
                'update_url' => 'https://example.com/updates/test-plugin-2',
            ],
        ]);

        // Mock HTTP responses
        Http::fake([
            'https://example.com/updates/test-plugin-1' => Http::response([
                'version' => '2.0.0',
                'download_url' => 'https://example.com/downloads/test-plugin-1-2.0.0.zip',
            ]),
            'https://example.com/updates/test-plugin-2' => Http::response([
                'version' => '1.0.0', // Same version, no update
            ]),
        ]);

        $updates = $this->updateManager->checkForUpdates();

        expect($updates)->toHaveKey('test-plugin-1')
            ->and($updates['test-plugin-1']['version'])->toBe('2.0.0')
            ->and($updates)->not->toHaveKey('test-plugin-2');
    });

    it('returns empty array when no updates available', function (): void {
        Plugin::create([
            'slug' => 'test-plugin',
            'name' => 'Test Plugin',
            'version' => '2.0.0',
            'meta' => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ]);

        Http::fake([
            'https://example.com/updates/test-plugin' => Http::response([
                'version' => '1.0.0', // Older version
            ]),
        ]);

        $updates = $this->updateManager->checkForUpdates();

        expect($updates)->toBeEmpty();
    });

    it('caches update check results', function (): void {
        Plugin::create([
            'slug' => 'test-plugin',
            'name' => 'Test Plugin',
            'version' => '1.0.0',
            'meta' => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ]);

        Http::fake([
            'https://example.com/updates/test-plugin' => Http::response([
                'version' => '2.0.0',
            ]),
        ]);

        // First call
        $this->updateManager->checkPluginUpdate('test-plugin');

        // Second call should use cache
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(['version' => '2.0.0']);

        $updateInfo = $this->updateManager->checkPluginUpdate('test-plugin');

        expect($updateInfo)->toBeArray();
    });
});

describe('Update Detection', function (): void {
    it('detects available updates using semantic versioning', function (): void {
        Plugin::create([
            'slug' => 'test-plugin',
            'name' => 'Test Plugin',
            'version' => '1.0.0',
            'meta' => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ]);

        Http::fake([
            'https://example.com/updates/test-plugin' => Http::response([
                'version' => '1.0.1',
            ]),
        ]);

        $updateInfo = $this->updateManager->checkPluginUpdate('test-plugin');

        expect($updateInfo)->not->toBeNull()
            ->and($updateInfo['version'])->toBe('1.0.1');
    });

    it('does not detect update when version is same', function (): void {
        Plugin::create([
            'slug' => 'test-plugin',
            'name' => 'Test Plugin',
            'version' => '1.0.0',
            'meta' => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ]);

        Http::fake([
            'https://example.com/updates/test-plugin' => Http::response([
                'version' => '1.0.0',
            ]),
        ]);

        $updateInfo = $this->updateManager->checkPluginUpdate('test-plugin');

        expect($updateInfo)->toBeNull();
    });

    it('does not detect update when version is older', function (): void {
        Plugin::create([
            'slug' => 'test-plugin',
            'name' => 'Test Plugin',
            'version' => '2.0.0',
            'meta' => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ]);

        Http::fake([
            'https://example.com/updates/test-plugin' => Http::response([
                'version' => '1.0.0',
            ]),
        ]);

        $updateInfo = $this->updateManager->checkPluginUpdate('test-plugin');

        expect($updateInfo)->toBeNull();
    });
});

describe('Update URL Handling', function (): void {
    it('returns null when plugin has no update URL', function (): void {
        Plugin::create([
            'slug' => 'test-plugin',
            'name' => 'Test Plugin',
            'version' => '1.0.0',
            'meta' => [], // No update_url
        ]);

        $updateInfo = $this->updateManager->checkPluginUpdate('test-plugin');

        expect($updateInfo)->toBeNull();
    });

    it('returns null when plugin does not exist', function (): void {
        $updateInfo = $this->updateManager->checkPluginUpdate('non-existent-plugin');

        expect($updateInfo)->toBeNull();
    });

    it('handles failed HTTP requests gracefully', function (): void {
        Plugin::create([
            'slug' => 'test-plugin',
            'name' => 'Test Plugin',
            'version' => '1.0.0',
            'meta' => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ]);

        Http::fake([
            'https://example.com/updates/test-plugin' => Http::response(null, 500),
        ]);

        $updateInfo = $this->updateManager->checkPluginUpdate('test-plugin');

        expect($updateInfo)->toBeNull();
    });

    it('handles network timeouts gracefully', function (): void {
        Plugin::create([
            'slug' => 'test-plugin',
            'name' => 'Test Plugin',
            'version' => '1.0.0',
            'meta' => [
                'update_url' => 'https://example.com/updates/test-plugin',
            ],
        ]);

        Http::fake([
            'https://example.com/updates/test-plugin' => function (): void {
                throw new Exception('Connection timeout');
            },
        ]);

        $updateInfo = $this->updateManager->checkPluginUpdate('test-plugin');

        expect($updateInfo)->toBeNull();
    });
});

describe('Version Comparison', function (): void {
    it('correctly compares major version changes', function (): void {
        $updateManager = new UpdateManager($this->pluginManager);
        $reflection = new ReflectionClass($updateManager);
        $method = $reflection->getMethod('isUpdateAvailable');
        $method->setAccessible(true);

        expect($method->invoke($updateManager, '1.0.0', '2.0.0'))->toBeTrue()
            ->and($method->invoke($updateManager, '2.0.0', '1.0.0'))->toBeFalse();
    });

    it('correctly compares minor version changes', function (): void {
        $updateManager = new UpdateManager($this->pluginManager);
        $reflection = new ReflectionClass($updateManager);
        $method = $reflection->getMethod('isUpdateAvailable');
        $method->setAccessible(true);

        expect($method->invoke($updateManager, '1.0.0', '1.1.0'))->toBeTrue()
            ->and($method->invoke($updateManager, '1.1.0', '1.0.0'))->toBeFalse();
    });

    it('correctly compares patch version changes', function (): void {
        $updateManager = new UpdateManager($this->pluginManager);
        $reflection = new ReflectionClass($updateManager);
        $method = $reflection->getMethod('isUpdateAvailable');
        $method->setAccessible(true);

        expect($method->invoke($updateManager, '1.0.0', '1.0.1'))->toBeTrue()
            ->and($method->invoke($updateManager, '1.0.1', '1.0.0'))->toBeFalse();
    });

    it('returns false for identical versions', function (): void {
        $updateManager = new UpdateManager($this->pluginManager);
        $reflection = new ReflectionClass($updateManager);
        $method = $reflection->getMethod('isUpdateAvailable');
        $method->setAccessible(true);

        expect($method->invoke($updateManager, '1.0.0', '1.0.0'))->toBeFalse();
    });
});
