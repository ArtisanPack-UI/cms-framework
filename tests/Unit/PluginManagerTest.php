<?php

use ArtisanPackUI\CMSFramework\Features\Plugins\PluginManager;
use ArtisanPackUI\CMSFramework\Features\Plugins\Plugin as BasePlugin;
use ArtisanPackUI\CMSFramework\Models\Plugin;
use ArtisanPackUI\CMSFramework\Features\Settings\SettingsManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;

// Test PluginManager constructor
it('initializes with correct paths', function () {
    // Mock the config function to return a specific plugin path
    $pluginPath = '/path/to/plugins';
    config(['cms.paths.plugins' => $pluginPath]);

    $manager = new PluginManager();

    // Use reflection to access protected property
    $reflection = new ReflectionClass($manager);
    $property = $reflection->getProperty('pluginPath');
    $property->setAccessible(true);

    expect($property->getValue($manager))->toBe($pluginPath);
});

// Test getAllInstalled method
it('can get all installed plugins', function () {
    // Create mock plugins
    $plugins = Plugin::factory()->count(3)->create();

    $manager = new PluginManager();
    $installedPlugins = $manager->getAllInstalled();

    expect($installedPlugins)->toHaveCount(3);
    expect($installedPlugins->first())->toBeInstanceOf(Plugin::class);
});

// Test getActiveInstance method
it('can get active plugin instance', function () {
    $manager = new PluginManager();

    // Create a mock plugin instance
    $mockPlugin = Mockery::mock(BasePlugin::class);
    $mockPlugin->slug = 'test-plugin';

    // Use reflection to set the protected loadedPluginInstances property
    $reflection = new ReflectionClass($manager);
    $property = $reflection->getProperty('loadedPluginInstances');
    $property->setAccessible(true);
    $property->setValue($manager, ['test-plugin' => $mockPlugin]);

    $instance = $manager->getActiveInstance('test-plugin');

    expect($instance)->toBe($mockPlugin);
});

// Test getActiveInstance returns null for non-existent plugin
it('returns null for non-existent active plugin', function () {
    $manager = new PluginManager();

    $instance = $manager->getActiveInstance('non-existent-plugin');

    expect($instance)->toBeNull();
});

// Test initializeActivePlugins method
it('initializes active plugins', function () {
    // Create mock plugin model
    $pluginModel = Plugin::factory()->create([
        'slug' => 'test-plugin',
        'is_active' => true,
    ]);

    // Create mock plugin instance
    $mockPlugin = Mockery::mock(BasePlugin::class);
    $mockPlugin->shouldReceive('register')->once();
    $mockPlugin->shouldReceive('boot')->once();
    $mockPlugin->shouldReceive('registerSettings')->andReturn([]);
    $mockPlugin->slug = 'test-plugin';

    // Mock the instance relationship on the plugin model
    $pluginModel->shouldReceive('instance')->andReturn($mockPlugin);

    // Mock Plugin::where to return a collection with our mock plugin
    Plugin::shouldReceive('where')
        ->with('is_active', true)
        ->andReturn(Mockery::mock('Illuminate\Database\Eloquent\Builder')
            ->shouldReceive('get')
            ->andReturn(collect([$pluginModel]))
            ->getMock());

    // Create a mock SettingsManager
    $settingsManager = Mockery::mock(SettingsManager::class);
    app()->instance(SettingsManager::class, $settingsManager);

    $manager = new PluginManager();
    $manager->initializeActivePlugins();

    // Verification is done through the shouldReceive expectations
    expect(true)->toBeTrue(); // Dummy assertion to avoid empty test
});

// Test activatePlugin method
it('can activate a plugin', function () {
    // Create a mock plugin model
    $pluginModel = Plugin::factory()->create([
        'slug' => 'test-plugin',
        'is_active' => false,
        'directory_name' => 'test-plugin',
    ]);

    // Create a mock plugin instance
    $mockPlugin = Mockery::mock(BasePlugin::class);
    $mockPlugin->shouldReceive('register')->once();
    $mockPlugin->shouldReceive('boot')->once();
    $mockPlugin->shouldReceive('registerMigrations')->andReturn(['database/migrations']);
    $mockPlugin->shouldReceive('registerSettings')->andReturn([]);
    $mockPlugin->slug = 'test-plugin';

    // Mock the instance relationship
    $pluginModel->shouldReceive('instance')->andReturn($mockPlugin);

    // Mock Plugin::where to return our plugin model
    Plugin::shouldReceive('where')
        ->with('slug', 'test-plugin')
        ->andReturn(Mockery::mock('Illuminate\Database\Eloquent\Builder')
            ->shouldReceive('firstOrFail')
            ->andReturn($pluginModel)
            ->getMock());

    // Mock Artisan facade
    Artisan::shouldReceive('call')->with('migrate', Mockery::any())->once();

    // Mock the update method on the plugin model
    $pluginModel->shouldReceive('update')
        ->with(['is_active' => true])
        ->once()
        ->andReturnSelf();

    // Create a mock SettingsManager
    $settingsManager = Mockery::mock(SettingsManager::class);
    app()->instance(SettingsManager::class, $settingsManager);

    // Mock other methods that would be called
    $manager = Mockery::mock(PluginManager::class)
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();
    $manager->shouldReceive('runComposerDumpAutoload')->once();
    $manager->shouldReceive('clearCaches')->once();

    $result = $manager->activatePlugin('test-plugin');

    expect($result)->toBe($pluginModel);
});

// Test deactivatePlugin method
it('can deactivate a plugin', function () {
    // Create a mock plugin model
    $pluginModel = Plugin::factory()->create([
        'slug' => 'test-plugin',
        'is_active' => true,
    ]);

    // Mock Plugin::where to return our plugin model
    Plugin::shouldReceive('where')
        ->with('slug', 'test-plugin')
        ->andReturn(Mockery::mock('Illuminate\Database\Eloquent\Builder')
            ->shouldReceive('firstOrFail')
            ->andReturn($pluginModel)
            ->getMock());

    // Mock the update method on the plugin model
    $pluginModel->shouldReceive('update')
        ->with(['is_active' => false])
        ->once()
        ->andReturnSelf();

    // Create a partial mock of PluginManager
    $manager = Mockery::mock(PluginManager::class)
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();
    $manager->shouldReceive('rollbackPluginMigrations')->with($pluginModel)->once();
    $manager->shouldReceive('runComposerDumpAutoload')->once();
    $manager->shouldReceive('clearCaches')->once();

    $result = $manager->deactivatePlugin('test-plugin');

    expect($result)->toBe($pluginModel);
});

// Test uninstallPlugin method
it('can uninstall a plugin', function () {
    // Create a mock plugin model
    $pluginModel = Plugin::factory()->create([
        'slug' => 'test-plugin',
        'is_active' => false,
        'directory_name' => 'test-plugin',
        'composer_package_name' => 'vendor/test-plugin',
    ]);

    // Mock Plugin::where to return our plugin model
    Plugin::shouldReceive('where')
        ->with('slug', 'test-plugin')
        ->andReturn(Mockery::mock('Illuminate\Database\Eloquent\Builder')
            ->shouldReceive('firstOrFail')
            ->andReturn($pluginModel)
            ->getMock());

    // Mock File facade
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('deleteDirectory')->once()->andReturn(true);

    // Mock the delete method on the plugin model
    $pluginModel->shouldReceive('delete')->once()->andReturn(true);

    // Create a partial mock of PluginManager
    $manager = Mockery::mock(PluginManager::class)
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();
    $manager->shouldReceive('removePluginFromAppComposer')->once();
    $manager->shouldReceive('runComposerDumpAutoload')->once();
    $manager->shouldReceive('clearCaches')->once();

    // Mock Log facade
    Log::shouldReceive('info')->times(2);

    $manager->uninstallPlugin('test-plugin');

    // Verification is done through the shouldReceive expectations
    expect(true)->toBeTrue(); // Dummy assertion to avoid empty test
});

// Test validateComposerJson method
it('validates composer.json structure', function () {
    // Create a temporary composer.json file
    $tempDir = sys_get_temp_dir() . '/plugin_test_' . uniqid();
    mkdir($tempDir);

    $composerJson = [
        'name' => 'vendor/test-plugin',
        'autoload' => [
            'psr-4' => [
                'Vendor\\TestPlugin\\' => 'src/'
            ]
        ]
    ];

    file_put_contents($tempDir . '/composer.json', json_encode($composerJson));

    // Mock File facade for exists check
    File::shouldReceive('exists')->with($tempDir . '/composer.json')->andReturn(true);
    File::shouldReceive('get')->with($tempDir . '/composer.json')->andReturn(json_encode($composerJson));

    $manager = new PluginManager();

    // Use reflection to access protected method
    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('validateComposerJson');
    $method->setAccessible(true);

    $result = $method->invoke($manager, $tempDir);

    expect($result)->toBe($composerJson);

    // Clean up
    unlink($tempDir . '/composer.json');
    rmdir($tempDir);
});

// Test clearCaches method
it('clears Laravel caches', function () {
    // Mock Artisan facade
    Artisan::shouldReceive('call')->with('cache:clear')->once();
    Artisan::shouldReceive('call')->with('view:clear')->once();
    Artisan::shouldReceive('call')->with('config:clear')->once();

    // Mock Log facade
    Log::shouldReceive('info')->with('Laravel caches cleared.')->once();

    $manager = new PluginManager();

    // Use reflection to access protected method
    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('clearCaches');
    $method->setAccessible(true);

    $method->invoke($manager);

    // Verification is done through the shouldReceive expectations
    expect(true)->toBeTrue(); // Dummy assertion to avoid empty test
});

// Clean up Mockery after each test
afterEach(function () {
    Mockery::close();
});
