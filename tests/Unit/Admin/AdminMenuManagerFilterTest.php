<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminMenuManager;
use Illuminate\Support\Facades\Gate;

beforeEach( function (): void {
    $this->manager = new AdminMenuManager;

    // Allow everything EXCEPT the sentinel used by the Gate re-check test,
    // so that test can prove enforceCapabilities strips filter-injected
    // entries the current user isn't allowed to see.
    Gate::before( fn ( ?Illuminate\Contracts\Auth\Authenticatable $user, string $ability ) => 'never_granted' === $ability ? null : true );

    // Reset the hooks registry between tests so filter callbacks don't leak.
    removeAllFilters( 'ap.admin.menu' );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.admin.menu' );
} );

describe( 'ap.admin.menu filter', function (): void {
    it( 'fires when getAdminMenu is called', function (): void {
        $called = false;
        addFilter( 'ap.admin.menu', function ( array $menu ) use ( &$called ): array {
            $called = true;

            return $menu;
        } );

        $this->manager->getAdminMenu();

        expect( $called )->toBeTrue();
    } );

    it( 'lets a subscriber inject a new top-level entry', function (): void {
        addFilter( 'ap.admin.menu', function ( array $menu ): array {
            $menu['plugin-entry'] = [
                'title' => 'Plugin Entry',
                'slug'  => 'plugin-entry',
                'label' => 'Plugin Entry',
                'url'   => '/admin/plugin-entry',
                'order' => 10,
            ];

            return $menu;
        } );

        $menu = $this->manager->getAdminMenu();

        expect( $menu )->toHaveKey( 'plugin-entry' )
            ->and( $menu['plugin-entry']['label'] )->toBe( 'Plugin Entry' );
    } );

    it( 'lets a subscriber remove an entry', function (): void {
        $this->manager->addPage( 'Dashboard', 'dashboard', null, ['icon' => 'x', 'capability' => ''] );

        addFilter( 'ap.admin.menu', function ( array $menu ): array {
            unset( $menu['dashboard'] );

            return $menu;
        } );

        $menu = $this->manager->getAdminMenu();

        expect( $menu )->not->toHaveKey( 'dashboard' );
    } );
} );

describe( 'filter Gate re-check', function (): void {
    it( 'drops filter-injected entries whose permission the user does not hold', function (): void {
        // The beforeEach's allow-all returns null (falls through to defines)
        // for the 'never_granted' sentinel, so Gate::allows resolves it to
        // false — exactly the state an unauthorized user would hit.
        addFilter( 'ap.admin.menu', function ( array $menu ): array {
            $menu['gated'] = [
                'title'      => 'Gated',
                'slug'       => 'gated',
                'label'      => 'Gated',
                'url'        => '/gated',
                'permission' => 'never_granted',
                'external'   => false,
            ];

            return $menu;
        } );

        $menu = $this->manager->getAdminMenu();

        expect( $menu )->not->toHaveKey( 'gated' );
    } );
} );

describe( 'top-level slug with slashes', function (): void {
    it( 'stores a route name with slashes replaced by dots', function (): void {
        Gate::before( fn ( ?Illuminate\Contracts\Auth\Authenticatable $user, string $ability ) => true );

        $this->manager->addPage( 'Reports', 'packages/reports', null, ['capability' => ''] );

        $menu = $this->manager->getAdminMenu();

        expect( $menu['packages/reports']['route'] )->toBe( 'admin.packages.reports' );
    } );
} );

describe( 'extended menu row shape', function (): void {
    it( 'adds label/iconId/permission/url/external to items', function (): void {
        $this->manager->addPage( 'Dashboard', 'dashboard', null, [
            'icon'       => 'fas.home',
            'capability' => '',
        ] );

        $menu = $this->manager->getAdminMenu();

        expect( $menu['dashboard'] )
            ->toHaveKey( 'label' )
            ->toHaveKey( 'iconId' )
            ->toHaveKey( 'url' )
            ->toHaveKey( 'external' );

        expect( $menu['dashboard']['label'] )->toBe( 'Dashboard' )
            ->and( $menu['dashboard']['iconId'] )->toBe( 'fas.home' )
            ->and( $menu['dashboard']['external'] )->toBeFalse();
    } );

    it( 'includes the permission field when the item has a capability', function (): void {
        Gate::define( 'view_admin_dashboard', fn ( $user = null ) => true );

        $this->manager->addPage( 'Dashboard', 'dashboard', null, [
            'icon'       => 'fas.home',
            'capability' => 'view_admin_dashboard',
        ] );

        $menu = $this->manager->getAdminMenu();

        expect( $menu['dashboard']['permission'] )->toBe( 'view_admin_dashboard' );
    } );

    it( 'keeps the pre-existing keys for Blade back-compat', function (): void {
        $this->manager->addPage( 'Dashboard', 'dashboard', null, ['icon' => 'fas.home', 'capability' => ''] );

        $menu = $this->manager->getAdminMenu();

        expect( $menu['dashboard'] )
            ->toHaveKey( 'title' )
            ->toHaveKey( 'route' )
            ->toHaveKey( 'icon' )
            ->toHaveKey( 'capability' );
    } );
} );
