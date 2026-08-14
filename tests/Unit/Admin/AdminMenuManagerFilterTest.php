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
    removeAllFilters( 'ap.cmsFramework.admin.menu' );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.cmsFramework.admin.menu' );
} );

describe( 'ap.cmsFramework.admin.menu filter', function (): void {
    it( 'fires when getAdminMenu is called', function (): void {
        $called = false;
        addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ) use ( &$called ): array {
            $called = true;

            return $menu;
        } );

        $this->manager->getAdminMenu();

        expect( $called )->toBeTrue();
    } );

    it( 'lets a subscriber inject a new top-level entry', function (): void {
        addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ): array {
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

        addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ): array {
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
        addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ): array {
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

describe( 'filter-injected URL sanitization', function (): void {
    it( 'collapses an unsafe scheme on an entry that carries no route', function (): void {
        // The documented way to inject a nav row is a bare array with a `url`
        // and no `route`. That shape skipped sanitization entirely, and
        // `cms::admin.partials.menu` renders `url` into an `<a href>`.
        addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ): array {
            $menu['evil'] = [
                'title' => 'Docs',
                'slug'  => 'evil',
                'label' => 'Docs',
                'url'   => 'javascript:alert(1)',
                'order' => 10,
            ];

            return $menu;
        } );

        $menu = $this->manager->getAdminMenu();

        expect( $menu['evil']['url'] )->toBe( '#' );
    } );

    it( 'collapses a scheme hidden behind characters the URL parser strips', function ( string $url ): void {
        // The URL parser removes tab/CR/LF anywhere and leading C0 controls
        // before resolving a scheme, and htmlspecialchars() leaves all of them
        // alone — so each of these reaches the browser as `javascript:` unless
        // the allow-list normalizes first.
        addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ) use ( $url ): array {
            $menu['evil'] = [
                'title' => 'Docs',
                'slug'  => 'evil',
                'label' => 'Docs',
                'url'   => $url,
                'order' => 10,
            ];

            return $menu;
        } );

        $menu = $this->manager->getAdminMenu();

        expect( $menu['evil']['url'] )->toBe( '#' );
    } )->with( [
        'tab'            => "java\tscript:alert(1)",
        'newline'        => "java\nscript:alert(1)",
        'carriage'       => "java\rscript:alert(1)",
        'leading C0'     => "\x01javascript:alert(1)",
        'mixed case'     => 'JaVaScRiPt:alert(1)',
        'trailing space' => ' javascript:alert(1) ',
    ] );

    it( 'sanitizes a non-string url that Blade would render unescaped', function (): void {
        // `e()` returns an Htmlable's toHtml() verbatim, so an unsanitized
        // HtmlString would skip the allow-list AND the escaping.
        addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ): array {
            $menu['evil'] = [
                'title' => 'Docs',
                'slug'  => 'evil',
                'label' => 'Docs',
                'url'   => new Illuminate\Support\HtmlString( 'javascript:alert(1)' ),
                'order' => 10,
            ];

            return $menu;
        } );

        $menu = $this->manager->getAdminMenu();

        expect( $menu['evil']['url'] )->toBe( '#' )
            ->and( $menu['evil']['url'] )->toBeString();
    } );

    it( 'coerces non-string label/title/menuTitle so Blade escapes them', function (): void {
        // `{{ $node['title'] }}` renders an Htmlable verbatim — the same
        // escaping bypass the url coercion closes. The text fields must arrive
        // as plain strings so `{{ }}` escapes them.
        addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ): array {
            $menu['evil'] = [
                'title'     => new Illuminate\Support\HtmlString( '<b>x</b>' ),
                'label'     => new Illuminate\Support\HtmlString( '<i>y</i>' ),
                'menuTitle' => new Illuminate\Support\HtmlString( '<u>z</u>' ),
                'slug'      => 'evil',
                'url'       => '/safe',
                'order'     => 10,
            ];

            return $menu;
        } );

        $menu = $this->manager->getAdminMenu();

        expect( $menu['evil']['title'] )->toBeString()->toBe( '<b>x</b>' )
            ->and( $menu['evil']['label'] )->toBeString()->toBe( '<i>y</i>' )
            ->and( $menu['evil']['menuTitle'] )->toBeString()->toBe( '<u>z</u>' );
    } );

    it( 'refuses a protocol-relative url that would navigate off-origin', function ( string $url ): void {
        addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ) use ( $url ): array {
            $menu['evil'] = [
                'title' => 'X',
                'slug'  => 'evil',
                'url'   => $url,
                'order' => 10,
            ];

            return $menu;
        } );

        expect( $this->manager->getAdminMenu()['evil']['url'] )->toBe( '#' );
    } )->with( [
        'protocol-relative'      => '//evil.example/phish',
        'backslash-protocol-rel' => '/\\evil.example/phish',
    ] );

    it( 'leaves a safe URL on a route-less entry untouched', function (): void {
        addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ): array {
            $menu['docs'] = [
                'title' => 'Docs',
                'slug'  => 'docs',
                'label' => 'Docs',
                'url'   => 'https://example.com/docs',
                'order' => 10,
            ];

            return $menu;
        } );

        $menu = $this->manager->getAdminMenu();

        expect( $menu['docs']['url'] )->toBe( 'https://example.com/docs' );
    } );

    it( 'reaches a URL nested inside a section', function (): void {
        $evil = [
            'title' => 'Docs',
            'slug'  => 'evil',
            'label' => 'Docs',
            'url'   => 'javascript:alert(1)',
            'order' => 10,
        ];

        $items = ['evil' => $evil];

        addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ) use ( $items ): array {
            $menu['tools'] = [
                'title' => 'Tools',
                'order' => 10,
                'items' => $items,
            ];

            return $menu;
        } );

        $menu = $this->manager->getAdminMenu();

        expect( $menu['tools']['items']['evil']['url'] )->toBe( '#' );
    } );

    it( 'reaches a URL nested two levels deep in subItems', function (): void {
        $evil = [
            'title' => 'Docs',
            'slug'  => 'evil',
            'label' => 'Docs',
            'url'   => "java\tscript:alert(1)",
            'order' => 10,
        ];

        $evilChild = ['evil' => $evil];

        $monthly = [
            'title'    => 'Monthly',
            'slug'     => 'monthly',
            'label'    => 'Monthly',
            'url'      => '/admin/reports/monthly',
            'order'    => 10,
            'subItems' => $evilChild,
        ];

        $subItems = ['monthly' => $monthly];

        addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ) use ( $subItems ): array {
            $menu['reports'] = [
                'title'    => 'Reports',
                'slug'     => 'reports',
                'label'    => 'Reports',
                'url'      => '/admin/reports',
                'order'    => 10,
                'subItems' => $subItems,
            ];

            return $menu;
        } );

        $menu = $this->manager->getAdminMenu();

        expect( $menu['reports']['subItems']['monthly']['subItems']['evil']['url'] )->toBe( '#' )
            ->and( $menu['reports']['subItems']['monthly']['url'] )->toBe( '/admin/reports/monthly' );
    } );

    it( 'keeps every navigation-safe URL form intact', function ( string $url ): void {
        addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ) use ( $url ): array {
            $menu['docs'] = [
                'title' => 'Docs',
                'slug'  => 'docs',
                'label' => 'Docs',
                'url'   => $url,
                'order' => 10,
            ];

            return $menu;
        } );

        $menu = $this->manager->getAdminMenu();

        expect( $menu['docs']['url'] )->toBe( $url );
    } )->with( [
        'absolute https' => 'https://example.com/docs',
        'absolute http'  => 'http://example.com/docs',
        'mailto'         => 'mailto:me@example.com',
        'tel'            => 'tel:+15555555555',
        'root relative'  => '/admin/reports',
        'fragment'       => '#section',
        'relative path'  => 'docs/index.html',
        // An interior space is not a scheme delimiter to the URL parser
        // either, so this stays a (harmless) relative reference.
        'spaced path'    => 'docs/my file.html',
    ] );
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
