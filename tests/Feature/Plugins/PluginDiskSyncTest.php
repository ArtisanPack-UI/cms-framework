<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginInstallationException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginNotFoundException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginValidationException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->manager         = app( PluginManager::class );
    $this->testPluginsPath = __DIR__ . '/../../Support/Plugins';
    $this->pluginsPath     = base_path( 'plugins' );

    File::ensureDirectoryExists( $this->pluginsPath );

    // Discovery is cached by default; disable so a copy made mid-test is seen.
    config()->set( 'cms.plugins.cacheEnabled', false );
} );

afterEach( function (): void {
    foreach ( [ 'valid-plugin', 'object-author-plugin' ] as $slug ) {
        if ( File::exists( $this->pluginsPath . '/' . $slug ) ) {
            File::deleteDirectory( $this->pluginsPath . '/' . $slug );
        }
    }
} );

describe( 'installFromDisk', function (): void {
    it( 'registers a disk-scaffolded plugin so it can be activated', function (): void {
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        expect( Plugin::where( 'slug', 'valid-plugin' )->exists() )->toBeFalse();

        $plugin = $this->manager->installFromDisk( 'valid-plugin' );

        expect( $plugin )->toBeInstanceOf( Plugin::class )
            ->and( $plugin->slug )->toBe( 'valid-plugin' )
            ->and( $plugin->name )->toBe( 'Valid Test Plugin' )
            ->and( $plugin->version )->toBe( '1.0.0' )
            ->and( $plugin->is_active )->toBeFalse()
            ->and( $plugin->meta )->toBeArray();

        // The end-to-end fix for #298: activation now resolves the row.
        expect( $this->manager->activate( 'valid-plugin' ) )->toBeTrue()
            ->and( Plugin::where( 'slug', 'valid-plugin' )->first()->is_active )->toBeTrue();
    } );

    it( 'fires the installing and installed hooks', function (): void {
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        $fired = [];
        addAction( 'ap.cmsFramework.plugin.installing', function ( string $slug ) use ( &$fired ): void {
            $fired['installing'] = $slug;
        } );
        addAction( 'ap.cmsFramework.plugin.installed', function ( string $slug ) use ( &$fired ): void {
            $fired['installed'] = $slug;
        } );

        $this->manager->installFromDisk( 'valid-plugin' );

        expect( $fired )->toBe( [
            'installing' => 'valid-plugin',
            'installed'  => 'valid-plugin',
        ] );
    } );

    it( 'throws when no plugin exists on disk for the slug', function (): void {
        expect( fn () => $this->manager->installFromDisk( 'not-on-disk' ) )
            ->toThrow( PluginNotFoundException::class );
    } );

    it( 'throws when the plugin is already registered', function (): void {
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        $this->manager->installFromDisk( 'valid-plugin' );

        expect( fn () => $this->manager->installFromDisk( 'valid-plugin' ) )
            ->toThrow( PluginInstallationException::class );
    } );

    it( 'throws when the manifest slug does not match the directory', function (): void {
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/object-author-plugin',
        );

        // The copied directory is named object-author-plugin but the manifest
        // still declares slug "valid-plugin".
        expect( fn () => $this->manager->installFromDisk( 'object-author-plugin' ) )
            ->toThrow( PluginValidationException::class );

        expect( Plugin::where( 'slug', 'object-author-plugin' )->exists() )->toBeFalse();
    } );
} );

describe( 'syncFromDisk', function (): void {
    it( 'installs every discovered plugin that has no row', function (): void {
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );
        File::copyDirectory(
            $this->testPluginsPath . '/object-author-plugin',
            $this->pluginsPath . '/object-author-plugin',
        );

        $results = $this->manager->syncFromDisk();

        $byslug = collect( $results )->keyBy( 'slug' );
        expect( $byslug['valid-plugin']['status'] )->toBe( 'installed' )
            ->and( $byslug['object-author-plugin']['status'] )->toBe( 'installed' )
            ->and( Plugin::where( 'slug', 'valid-plugin' )->exists() )->toBeTrue()
            ->and( Plugin::where( 'slug', 'object-author-plugin' )->exists() )->toBeTrue();
    } );

    it( 'is idempotent and reports unchanged on a second run', function (): void {
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        $this->manager->syncFromDisk();
        $results = $this->manager->syncFromDisk();

        expect( collect( $results )->firstWhere( 'slug', 'valid-plugin' )['status'] )
            ->toBe( 'unchanged' )
            ->and( Plugin::where( 'slug', 'valid-plugin' )->count() )->toBe( 1 );
    } );

    it( 'preserves activation state for a plugin already registered', function (): void {
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        $this->manager->installFromDisk( 'valid-plugin' );
        $this->manager->activate( 'valid-plugin' );

        $this->manager->syncFromDisk();

        expect( Plugin::where( 'slug', 'valid-plugin' )->first()->is_active )->toBeTrue();
    } );

    it( 'refreshes manifest fields for an existing row without touching is_active', function (): void {
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        $this->manager->installFromDisk( 'valid-plugin' );
        $this->manager->activate( 'valid-plugin' );

        // Bump the on-disk manifest version.
        $manifestPath        = $this->pluginsPath . '/valid-plugin/plugin.json';
        $manifest            = json_decode( File::get( $manifestPath ), true );
        $manifest['version'] = '1.1.0';
        File::put( $manifestPath, json_encode( $manifest ) );

        $results = $this->manager->syncFromDisk();

        $row = Plugin::where( 'slug', 'valid-plugin' )->first();
        expect( collect( $results )->firstWhere( 'slug', 'valid-plugin' )['status'] )->toBe( 'updated' )
            ->and( $row->version )->toBe( '1.1.0' )
            ->and( $row->is_active )->toBeTrue();
    } );

    it( 'reports a failure when a manifest is invalid and installs nothing for it', function (): void {
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/object-author-plugin',
        );

        $results = collect( $this->manager->syncFromDisk() )->keyBy( 'slug' );

        expect( $results['object-author-plugin']['status'] )->toBe( 'failed' )
            ->and( Plugin::where( 'slug', 'object-author-plugin' )->exists() )->toBeFalse();
    } );
} );

describe( 'cms:plugins:sync command', function (): void {
    it( 'registers discovered plugins and reports a summary', function (): void {
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/valid-plugin',
        );

        $this->artisan( 'cms:plugins:sync' )
            ->assertExitCode( 0 )
            ->expectsOutputToContain( 'valid-plugin' );

        expect( Plugin::where( 'slug', 'valid-plugin' )->exists() )->toBeTrue();
    } );

    it( 'exits with a failure code when a plugin fails to sync', function (): void {
        File::copyDirectory(
            $this->testPluginsPath . '/valid-plugin',
            $this->pluginsPath . '/object-author-plugin',
        );

        $this->artisan( 'cms:plugins:sync' )->assertExitCode( 1 );
    } );
} );
