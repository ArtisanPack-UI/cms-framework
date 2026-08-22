<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginValidationException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->manager         = new PluginManager;
    $this->testPluginsPath = __DIR__ . '/../../Support/Plugins';
} );

describe( 'Plugin Discovery', function (): void {
    it( 'discovers plugins from filesystem', function (): void {
        // Copy test plugins to plugins directory
        $pluginsPath = base_path( 'plugins' );
        File::ensureDirectoryExists( $pluginsPath );
        File::copyDirectory( $this->testPluginsPath . '/valid-plugin', $pluginsPath . '/valid-plugin' );

        $plugins = $this->manager->discoverPlugins();

        expect( $plugins )->toBeArray()
            ->and( count( $plugins ) )->toBeGreaterThan( 0 );

        // Find our test plugin
        $testPlugin = collect( $plugins )->firstWhere( 'slug', 'valid-plugin' );
        expect( $testPlugin )->not->toBeNull()
            ->and( $testPlugin['name'] )->toBe( 'Valid Test Plugin' )
            ->and( $testPlugin['version'] )->toBe( '1.0.0' );

        // Cleanup
        File::deleteDirectory( $pluginsPath . '/valid-plugin' );
    } );

    it( 'merges database status with discovered plugins', function (): void {
        $pluginsPath = base_path( 'plugins' );
        File::ensureDirectoryExists( $pluginsPath );
        File::copyDirectory( $this->testPluginsPath . '/valid-plugin', $pluginsPath . '/valid-plugin' );

        // Create database entry
        Plugin::create( [
            'slug'      => 'valid-plugin',
            'name'      => 'Valid Test Plugin',
            'version'   => '1.0.0',
            'is_active' => true,
        ] );

        $plugins    = $this->manager->discoverPlugins();
        $testPlugin = collect( $plugins )->firstWhere( 'slug', 'valid-plugin' );

        expect( $testPlugin['is_active'] )->toBeTrue();

        // Cleanup
        File::deleteDirectory( $pluginsPath . '/valid-plugin' );
    } );

    it( 'returns empty array when plugins directory does not exist', function (): void {
        // Temporarily rename plugins directory
        $pluginsPath = base_path( 'plugins' );
        $tempPath    = base_path( 'plugins-temp' );

        if ( File::exists( $pluginsPath ) ) {
            File::move( $pluginsPath, $tempPath );
        }

        $plugins = $this->manager->discoverPlugins();

        expect( $plugins )->toBeArray()
            ->and( $plugins )->toBeEmpty();

        // Restore
        if ( File::exists( $tempPath ) ) {
            File::move( $tempPath, $pluginsPath );
        }
    } );
} );

describe( 'Get Plugin', function (): void {
    it( 'retrieves a specific plugin by slug', function (): void {
        $pluginsPath = base_path( 'plugins' );
        File::ensureDirectoryExists( $pluginsPath );
        File::copyDirectory( $this->testPluginsPath . '/valid-plugin', $pluginsPath . '/valid-plugin' );

        $plugin = $this->manager->getPlugin( 'valid-plugin' );

        expect( $plugin )->toBeArray()
            ->and( $plugin['slug'] )->toBe( 'valid-plugin' )
            ->and( $plugin['name'] )->toBe( 'Valid Test Plugin' );

        // Cleanup
        File::deleteDirectory( $pluginsPath . '/valid-plugin' );
    } );

    it( 'prevents path traversal with invalid slug', function (): void {
        $plugin = $this->manager->getPlugin( '../../../etc/passwd' );

        expect( $plugin )->toBeNull();
    } );

    it( 'prevents path traversal with dot notation', function (): void {
        $plugin = $this->manager->getPlugin( '../../vendor' );

        expect( $plugin )->toBeNull();
    } );

    it( 'returns null for non-existent plugin', function (): void {
        $plugin = $this->manager->getPlugin( 'non-existent-plugin' );

        expect( $plugin )->toBeNull();
    } );

    it( 'validates slug format', function (): void {
        // Invalid characters
        expect( $this->manager->getPlugin( 'plugin/with/slashes' ) )->toBeNull()
            ->and( $this->manager->getPlugin( 'plugin with spaces' ) )->toBeNull()
            ->and( $this->manager->getPlugin( 'plugin@special' ) )->toBeNull();

        // Valid characters
        $pluginsPath = base_path( 'plugins' );
        File::ensureDirectoryExists( $pluginsPath );
        File::copyDirectory( $this->testPluginsPath . '/valid-plugin', $pluginsPath . '/valid-plugin' );

        expect( $this->manager->getPlugin( 'valid-plugin' ) )->not->toBeNull();

        File::deleteDirectory( $pluginsPath . '/valid-plugin' );
    } );
} );

describe( 'Author Name Resolution', function (): void {
    it( 'exposes a display-safe author_name for a string author', function (): void {
        $pluginsPath = base_path( 'plugins' );
        File::ensureDirectoryExists( $pluginsPath );
        File::copyDirectory( $this->testPluginsPath . '/valid-plugin', $pluginsPath . '/valid-plugin' );

        $plugin = $this->manager->getPlugin( 'valid-plugin' );

        expect( $plugin['author'] )->toBe( 'Test Author' )
            ->and( $plugin['author_name'] )->toBe( 'Test Author' )
            ->and( $plugin['author_name'] )->toBeString();

        File::deleteDirectory( $pluginsPath . '/valid-plugin' );
    } );

    it( 'flattens an object-form author into author_name', function (): void {
        $pluginsPath = base_path( 'plugins' );
        File::ensureDirectoryExists( $pluginsPath );
        File::copyDirectory( $this->testPluginsPath . '/object-author-plugin', $pluginsPath . '/object-author-plugin' );

        $plugin = $this->manager->getPlugin( 'object-author-plugin' );

        expect( $plugin['author'] )->toBeArray()
            ->and( $plugin['author']['name'] )->toBe( 'Jane Developer' )
            ->and( $plugin['author_name'] )->toBe( 'Jane Developer' )
            ->and( $plugin['author_name'] )->toBeString();

        File::deleteDirectory( $pluginsPath . '/object-author-plugin' );
    } );

    it( 'includes author_name for every discovered plugin', function (): void {
        $pluginsPath = base_path( 'plugins' );
        File::ensureDirectoryExists( $pluginsPath );
        File::copyDirectory( $this->testPluginsPath . '/object-author-plugin', $pluginsPath . '/object-author-plugin' );

        $plugins    = $this->manager->discoverPlugins();
        $discovered = collect( $plugins )->firstWhere( 'slug', 'object-author-plugin' );

        expect( $discovered['author_name'] )->toBe( 'Jane Developer' )
            ->and( $discovered['author_name'] )->toBeString();

        File::deleteDirectory( $pluginsPath . '/object-author-plugin' );
    } );
} );

describe( 'Manifest Validation', function (): void {
    it( 'validates required fields', function (): void {
        $invalidManifest = ['name' => 'Test'];

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$invalidManifest] ) )
            ->toThrow( PluginValidationException::class, 'Missing required field: slug' );
    } );

    it( 'validates slug format in manifest', function (): void {
        $manifest = [
            'slug'    => 'invalid slug with spaces',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
        ];

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'Invalid slug format' );
    } );

    it( 'validates version format', function (): void {
        $manifest = [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => 'invalid-version',
        ];

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'Invalid version format' );
    } );

    it( 'accepts valid semver versions', function (): void {
        $manifest = [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.2.3',
        ];

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->not->toThrow( PluginValidationException::class );
    } );
} );

describe( 'Manifest Parsing', function (): void {
    it( 'parses valid JSON manifest', function (): void {
        $pluginsPath = base_path( 'plugins' );
        File::ensureDirectoryExists( $pluginsPath );
        File::copyDirectory( $this->testPluginsPath . '/valid-plugin', $pluginsPath . '/valid-plugin' );

        $manifestPath = $pluginsPath . '/valid-plugin/plugin.json';
        $manifest     = invokeMethod( $this->manager, 'parseManifest', [$manifestPath] );

        expect( $manifest )->toBeArray()
            ->and( $manifest['slug'] )->toBe( 'valid-plugin' )
            ->and( $manifest['name'] )->toBe( 'Valid Test Plugin' );

        File::deleteDirectory( $pluginsPath . '/valid-plugin' );
    } );

    it( 'returns null for non-existent manifest', function (): void {
        $manifest = invokeMethod( $this->manager, 'parseManifest', ['/non/existent/path/plugin.json'] );

        expect( $manifest )->toBeNull();
    } );

    it( 'returns null for invalid JSON', function (): void {
        $tempFile = storage_path( 'app/invalid-manifest.json' );
        File::put( $tempFile, '{invalid json}' );

        $manifest = invokeMethod( $this->manager, 'parseManifest', [$tempFile] );

        expect( $manifest )->toBeNull();

        File::delete( $tempFile );
    } );
} );
