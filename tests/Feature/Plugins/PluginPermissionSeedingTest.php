<?php

/**
 * Guards the fix for #356: plugin permission seeding must locate
 * artisanpack-ui/rbac's Permission model by its correctly-cased name.
 *
 * The locator previously checked `ArtisanPackUI\RBAC\Models\Permission`. PHP
 * class names are case-insensitive, so that only resolved when the real
 * `ArtisanPackUI\Rbac\Models\Permission` class was already loaded — Composer's
 * autoloader is case-sensitive and misses the mis-cased name otherwise.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.13.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use ArtisanPackUI\Rbac\Models\Permission as RbacPermission;

beforeEach( function (): void {
    $this->createPlugin = function ( bool $isActive = false ): Plugin {
        $manifest = [
            'slug'        => 'perm-probe',
            'name'        => 'Perm Probe',
            'version'     => '1.0.0',
            'permissions' => [ 'perm-probe.reports.view', 'perm-probe.reports.export' ],
        ];

        return Plugin::create( [
            'slug'      => $manifest['slug'],
            'name'      => $manifest['name'],
            'version'   => $manifest['version'],
            'is_active' => $isActive,
            'meta'      => $manifest,
        ] );
    };
} );

it( 'resolves the rbac permission model by its exact declared class name', function (): void {
    $manager = new class extends PluginManager {
        public function exposedRbacPermissionModel(): ?string
        {
            return $this->rbacPermissionModel();
        }
    };

    // Exact, case-sensitive match — a mis-cased namespace would compare unequal.
    expect( $manager->exposedRbacPermissionModel() )->toBe( 'ArtisanPackUI\\Rbac\\Models\\Permission' )
        ->and( $manager->exposedRbacPermissionModel() )->toBe( RbacPermission::class );
} );

it( 'seeds declared permissions when a plugin is activated', function (): void {
    ( $this->createPlugin )();

    $result = app( PluginManager::class )->activate( 'perm-probe' );

    expect( $result )->toBeTrue()
        ->and( RbacPermission::whereIn( 'slug', [ 'perm-probe.reports.view', 'perm-probe.reports.export' ] )->count() )->toBe( 2 );
} );

it( 'removes seeded permissions when a plugin is deleted', function (): void {
    ( $this->createPlugin )();
    RbacPermission::create( [ 'name' => 'perm-probe.reports.view', 'slug' => 'perm-probe.reports.view' ] );
    RbacPermission::create( [ 'name' => 'perm-probe.reports.export', 'slug' => 'perm-probe.reports.export' ] );
    RbacPermission::create( [ 'name' => 'unrelated.manage', 'slug' => 'unrelated.manage' ] );

    app( PluginManager::class )->delete( 'perm-probe', false );

    expect( RbacPermission::where( 'slug', 'like', 'perm-probe.%' )->exists() )->toBeFalse()
        ->and( RbacPermission::where( 'slug', 'unrelated.manage' )->exists() )->toBeTrue();
} );
