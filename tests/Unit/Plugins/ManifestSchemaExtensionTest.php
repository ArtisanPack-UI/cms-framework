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

describe( 'update', function (): void {
    it( 'accepts an owner/repo shorthand', function (): void {
        $manifest = array_merge( $this->base, [
            'update' => ['github' => 'ArtisanPack-UI/artisanpack-ui-plugin'],
        ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->not->toThrow( PluginValidationException::class );
    } );

    it( 'accepts a full github repository URL', function (): void {
        $manifest = array_merge( $this->base, [
            'update' => ['github' => 'https://github.com/ArtisanPack-UI/artisanpack-ui-plugin'],
        ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->not->toThrow( PluginValidationException::class );
    } );

    it( 'accepts a non-github url so other sources fall out of the same key', function (): void {
        $manifest = array_merge( $this->base, [
            'update' => ['url' => 'https://gitlab.com/owner/repo'],
        ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->not->toThrow( PluginValidationException::class );
    } );

    it( 'rejects a non-object value', function (): void {
        $manifest = array_merge( $this->base, ['update' => 'ArtisanPack-UI/artisanpack-ui-plugin'] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'Invalid update.' );
    } );

    it( 'rejects an object declaring neither github nor url', function (): void {
        $manifest = array_merge( $this->base, ['update' => ['gitea' => 'owner/repo']] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'Must declare' );
    } );

    it( 'rejects a malformed github shorthand', function (): void {
        $manifest = array_merge( $this->base, ['update' => ['github' => 'not-a-repo']] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'Invalid update.github' );
    } );

    it( 'rejects dot-only shorthand segments', function ( string $shorthand ): void {
        $manifest = array_merge( $this->base, ['update' => ['github' => $shorthand]] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'Invalid update.github' );
    } )->with( [
        'traversal pair'  => '../..',
        'dot owner'       => './repo',
        'dot repo'        => 'owner/.',
        'single dot pair' => './.',
    ] );

    it( 'still accepts dots inside otherwise-valid segments', function (): void {
        $manifest = array_merge( $this->base, ['update' => ['github' => 'ArtisanPack-UI/cms.framework']] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->not->toThrow( PluginValidationException::class );
    } );

    it( 'rejects a plaintext http url', function (): void {
        $manifest = array_merge( $this->base, ['update' => ['url' => 'http://example.com/updates.json']] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'Invalid update.url' );
    } );

    it( 'rejects a plaintext http github url', function (): void {
        $manifest = array_merge( $this->base, ['update' => ['github' => 'http://github.com/owner/repo']] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'Invalid update.github' );
    } );
} );

describe( 'requires', function (): void {
    it( 'accepts a well-formed requires block', function (): void {
        $manifest = array_merge( $this->base, [
            'requires' => [
                'cms-framework' => '^2.0',
                'plugins'       => ['base-forms' => '^1.5', 'file-uploads' => '^2.0'],
            ],
        ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->not->toThrow( PluginValidationException::class );
    } );

    it( 'accepts an empty requires object', function (): void {
        $manifest = array_merge( $this->base, ['requires' => []] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->not->toThrow( PluginValidationException::class );
    } );

    it( 'rejects a non-object requires', function (): void {
        $manifest = array_merge( $this->base, ['requires' => 'base-forms'] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'Invalid requires.' );
    } );

    it( 'rejects a non-string cms-framework constraint', function (): void {
        $manifest = array_merge( $this->base, ['requires' => ['cms-framework' => 2]] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'requires.cms-framework' );
    } );

    it( 'rejects requires.plugins expressed as a list', function (): void {
        $manifest = array_merge( $this->base, ['requires' => ['plugins' => ['base-forms']]] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'requires.plugins' );
    } );

    it( 'rejects an empty version constraint', function (): void {
        $manifest = array_merge( $this->base, ['requires' => ['plugins' => ['base-forms' => '']]] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'constraint' );
    } );

    it( 'rejects a plugin requiring its own slug', function (): void {
        $manifest = array_merge( $this->base, ['requires' => ['plugins' => ['test-plugin' => '^1.0']]] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'cannot reference its own slug' );
    } );
} );

describe( 'conflicts', function (): void {
    it( 'accepts a well-formed conflicts map', function (): void {
        $manifest = array_merge( $this->base, ['conflicts' => ['legacy-forms' => '*']] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->not->toThrow( PluginValidationException::class );
    } );

    it( 'rejects a conflicts key with an invalid slug', function (): void {
        $manifest = array_merge( $this->base, ['conflicts' => ['bad slug' => '*']] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'conflicts' );
    } );

    it( 'rejects a plugin conflicting with its own slug', function (): void {
        $manifest = array_merge( $this->base, ['conflicts' => ['test-plugin' => '*']] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'cannot reference its own slug' );
    } );
} );

describe( 'composer', function (): void {
    it( 'accepts a well-formed composer block', function (): void {
        $manifest = array_merge( $this->base, [
            'composer' => [ 'artisanpack-ui/convertkit' => '^1.2' ],
        ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->not->toThrow( PluginValidationException::class );
    } );

    it( 'accepts an empty composer object', function (): void {
        $manifest = array_merge( $this->base, [ 'composer' => [] ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->not->toThrow( PluginValidationException::class );
    } );

    it( 'rejects a non-object composer', function (): void {
        $manifest = array_merge( $this->base, [ 'composer' => 'artisanpack-ui/convertkit' ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'Invalid composer.' );
    } );

    it( 'rejects composer expressed as a list', function (): void {
        $manifest = array_merge( $this->base, [ 'composer' => [ 'artisanpack-ui/convertkit' ] ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'Invalid composer.' );
    } );

    it( 'rejects a key that is not a valid Composer package name', function (): void {
        $manifest = array_merge( $this->base, [ 'composer' => [ 'not-a-package' => '^1.0' ] ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'valid Composer package name' );
    } );

    it( 'rejects a package name with a shell fragment', function (): void {
        $manifest = array_merge( $this->base, [ 'composer' => [ 'vendor/pkg; rm -rf /' => '^1.0' ] ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'valid Composer package name' );
    } );

    it( 'rejects an empty version constraint', function (): void {
        $manifest = array_merge( $this->base, [ 'composer' => [ 'artisanpack-ui/convertkit' => '' ] ] );

        expect( fn () => invokeMethod( $this->manager, 'validateManifest', [$manifest] ) )
            ->toThrow( PluginValidationException::class, 'constraint' );
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

    it( 'exposes dependency and conflict manifest fields', function (): void {
        $plugin = new Plugin( [
            'slug'    => 'advanced-forms',
            'name'    => 'Advanced Forms',
            'version' => '2.0.0',
            'meta'    => [
                'requires'  => [
                    'cms-framework' => '^2.0',
                    'plugins'       => ['base-forms' => '^1.5'],
                ],
                'conflicts' => ['legacy-forms' => '*'],
                'composer'  => ['artisanpack-ui/convertkit' => '^1.2'],
            ],
        ] );

        expect( $plugin->required_plugins )->toBe( ['base-forms' => '^1.5'] )
            ->and( $plugin->required_host_version )->toBe( '^2.0' )
            ->and( $plugin->conflicting_plugins )->toBe( ['legacy-forms' => '*'] )
            ->and( $plugin->required_composer_packages )->toBe( ['artisanpack-ui/convertkit' => '^1.2'] );
    } );

    it( 'returns empty dependency maps when absent', function (): void {
        $plugin = new Plugin( ['slug' => 'x', 'name' => 'X', 'version' => '1.0.0', 'meta' => []] );

        expect( $plugin->required_plugins )->toBe( [] )
            ->and( $plugin->required_host_version )->toBeNull()
            ->and( $plugin->conflicting_plugins )->toBe( [] )
            ->and( $plugin->required_composer_packages )->toBe( [] );
    } );
} );
