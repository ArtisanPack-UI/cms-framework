<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginValidationException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;

beforeEach( function (): void {
    $this->manager = new PluginManager;
    $this->base    = [
        'slug'    => 'test-plugin',
        'name'    => 'Test Plugin',
        'version' => '1.0.0',
    ];
} );

describe( 'min_host_version', function (): void {
    it( 'accepts a valid semver', function (): void {
        $manifest = array_merge( $this->base, ['min_host_version' => '2.4.0'] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->not->toThrow( PluginValidationException::class );
    } );

    it( 'rejects a malformed version string', function (): void {
        $manifest = array_merge( $this->base, ['min_host_version' => 'v2.4'] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'Invalid min_host_version' );
    } );
} );

describe( 'federated_module', function (): void {
    it( 'accepts an entry-only descriptor', function (): void {
        $manifest = array_merge( $this->base, [
            'federated_module' => ['entry' => 'dist/remoteEntry.js'],
        ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->not->toThrow( PluginValidationException::class );
    } );

    it( 'rejects a missing entry', function (): void {
        $manifest = array_merge( $this->base, ['federated_module' => ['exposes' => ['a']]] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'Invalid federated_module' );
    } );

    it( 'rejects a non-array exposes', function (): void {
        $manifest = array_merge( $this->base, [
            'federated_module' => ['entry' => 'x.js', 'exposes' => 'oops'],
        ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'exposes' );
    } );
} );

describe( 'nav_entries', function (): void {
    it( 'accepts a well-formed list', function (): void {
        $manifest = array_merge( $this->base, [
            'nav_entries' => [
                ['slug' => 'reports', 'label' => 'Reports'],
            ],
        ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->not->toThrow( PluginValidationException::class );
    } );

    it( 'rejects entries missing slug/label', function (): void {
        $manifest = array_merge( $this->base, [
            'nav_entries' => [
                ['slug' => 'reports'],
            ],
        ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'nav_entries[0]' );
    } );

    it( 'rejects associative arrays', function (): void {
        $manifest = array_merge( $this->base, [
            'nav_entries' => ['not' => 'a list'],
        ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class );
    } );
} );

describe( 'permissions', function (): void {
    it( 'accepts a list of properly-namespaced slugs', function (): void {
        $manifest = array_merge( $this->base, [
            'permissions' => ['test-plugin.reports.view', 'test-plugin.reports.export'],
        ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->not->toThrow( PluginValidationException::class );
    } );

    it( 'rejects non-string entries', function (): void {
        $manifest = array_merge( $this->base, ['permissions' => ['test-plugin.reports.view', 42]] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class );
    } );

    it( 'rejects permissions that are not prefixed with the plugin slug', function (): void {
        // Without this rule a plugin could name framework-owned permissions
        // ('manage_users') and delete them on uninstall.
        $manifest = array_merge( $this->base, [
            'permissions' => ['manage_users'],
        ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'must be prefixed' );
    } );
} );

describe( 'migrations_path', function (): void {
    it( 'accepts a valid relative path', function (): void {
        $manifest = array_merge( $this->base, ['migrations_path' => 'database/migrations'] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->not->toThrow( PluginValidationException::class );
    } );

    it( 'rejects a traversal attempt', function (): void {
        $manifest = array_merge( $this->base, ['migrations_path' => '../../database/migrations'] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'no ".."' );
    } );

    it( 'rejects an absolute path', function (): void {
        $manifest = array_merge( $this->base, ['migrations_path' => '/etc/passwd'] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class );
    } );
} );

describe( 'Plugin model accessors', function (): void {
    it( 'exposes the new manifest fields ergonomically', function (): void {
        $plugin = new Plugin( [
            'slug'    => 'test-plugin',
            'name'    => 'Test Plugin',
            'version' => '1.0.0',
            'meta'    => [
                'min_host_version'              => '2.4.0',
                'federated_module'              => ['entry' => 'dist/remoteEntry.js', 'exposes' => ['./App']],
                'nav_entries'                   => [['slug' => 'x', 'label' => 'X']],
                'permissions'                   => ['test-plugin.reports.view'],
                'rollback_migrations_on_delete' => true,
            ],
        ] );

        expect( $plugin->min_host_version )->toBe( '2.4.0' )
            ->and( $plugin->federated_module )->toMatchArray( ['entry' => 'dist/remoteEntry.js'] )
            ->and( $plugin->nav_entries )->toHaveCount( 1 )
            ->and( $plugin->declared_permissions )->toBe( ['test-plugin.reports.view'] )
            ->and( $plugin->rollback_migrations_on_delete )->toBeTrue();
    } );

    it( 'returns sensible defaults when manifest fields are absent', function (): void {
        $plugin = new Plugin( ['slug' => 'x', 'name' => 'X', 'version' => '1.0.0', 'meta' => []] );

        expect( $plugin->min_host_version )->toBeNull()
            ->and( $plugin->federated_module )->toBeNull()
            ->and( $plugin->nav_entries )->toBe( [] )
            ->and( $plugin->declared_permissions )->toBe( [] )
            ->and( $plugin->rollback_migrations_on_delete )->toBeFalse();
    } );
} );
