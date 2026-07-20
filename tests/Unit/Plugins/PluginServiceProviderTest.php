<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminMenuManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\PluginRegistry;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\PluginServiceProvider;
use Illuminate\Support\Facades\Gate;

beforeEach( function (): void {
    Gate::before( fn ( ?Illuminate\Contracts\Auth\Authenticatable $user, string $ability ) => true );

    removeAllFilters( 'ap.cmsFramework.admin.menu' );
    removeAllFilters( 'ap.plugins.federatedModules' );

    $this->app->singleton( AdminMenuManager::class, fn () => new AdminMenuManager );
    $this->app->singleton( PluginRegistry::class );

    // Wire the same single-callback filter the framework's PluginsServiceProvider
    // sets up, so registry contents surface in the admin menu.
    $registry = app( PluginRegistry::class );
    addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ) use ( $registry ): array {
        foreach ( $registry->navEntries() as $slug => $entry ) {
            $menu[ $slug ] = array_merge( $entry, $menu[ $slug ] ?? [] );
        }

        return $menu;
    } );
    addFilter( 'ap.plugins.federatedModules', function ( array $modules ) use ( $registry ): array {
        return array_merge( $modules, $registry->federatedModules() );
    } );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.cmsFramework.admin.menu' );
    removeAllFilters( 'ap.plugins.federatedModules' );
} );

it( 'registers an admin page through the AdminMenuManager', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerAdminPage( 'my-plugin', [
                'title'      => 'My Plugin',
                'icon'       => 'fas.star',
                'capability' => '',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'my-plugin'];
        }
    };

    $provider->boot();

    $menu = app( AdminMenuManager::class )->getAdminMenu();

    expect( $menu )->toHaveKey( 'my-plugin' )
        ->and( $menu['my-plugin']['label'] )->toBe( 'My Plugin' );
} );

it( 'injects a nav entry via the ap.cmsFramework.admin.menu filter', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerNavEntry( [
                'slug'       => 'reports',
                'label'      => 'Reports',
                'url'        => '/admin/reports',
                'icon'       => 'fas.chart-bar',
                'permission' => 'reports.view',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'reports-plugin'];
        }
    };

    $provider->boot();

    $menu = app( AdminMenuManager::class )->getAdminMenu();

    expect( $menu )->toHaveKey( 'reports' )
        ->and( $menu['reports']['url'] )->toBe( '/admin/reports' )
        ->and( $menu['reports']['permission'] )->toBe( 'reports.view' )
        ->and( $menu['reports']['external'] )->toBeFalse();
} );

it( 'sanitizes javascript: URLs in nav entries down to #', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerNavEntry( [
                'slug'  => 'evil',
                'label' => 'Docs',
                'url'   => 'javascript:alert(1)',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'evil-plugin'];
        }
    };

    $provider->boot();

    $menu = app( AdminMenuManager::class )->getAdminMenu();

    expect( $menu['evil']['url'] )->toBe( '#' );
} );

it( 'is idempotent when the same nav entry is registered twice', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerNavEntry( [
                'slug'  => 'reports',
                'label' => 'Reports',
                'url'   => '/admin/reports',
            ] );
            $this->registerNavEntry( [
                'slug'  => 'reports',
                'label' => 'Reports v2',
                'url'   => '/admin/reports',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'reports-plugin'];
        }
    };

    $provider->boot();

    $entries = app( PluginRegistry::class )->navEntries();

    expect( $entries )->toHaveCount( 1 )
        ->and( $entries['reports']['label'] )->toBe( 'Reports v2' );
} );

it( 'exposes federated modules via a filter', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerFederatedModule( 'reports', 'dist/remoteEntry.js', ['./App'] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'reports-plugin'];
        }
    };

    $provider->boot();

    $modules = applyFilters( 'ap.plugins.federatedModules', [] );

    expect( $modules )->toHaveKey( 'reports' )
        ->and( $modules['reports']['entry'] )->toBe( 'dist/remoteEntry.js' )
        ->and( $modules['reports']['exposes'] )->toBe( ['./App'] );
} );

it( 'resolves pluginPath and pluginConfig from the manifest', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
        }

        public function exposePluginPath( ?string $subpath = null ): string
        {
            return $this->pluginPath( $subpath );
        }

        public function exposePluginConfig( ?string $key = null ): mixed
        {
            return $this->pluginConfig( $key );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = [
                'slug'   => 'my-plugin',
                'name'   => 'My Plugin',
                'nested' => ['key' => 'value'],
            ];
        }
    };

    expect( $provider->exposePluginPath() )->toEndWith( '/plugins/my-plugin' )
        ->and( $provider->exposePluginPath( 'resources/js' ) )->toEndWith( '/plugins/my-plugin/resources/js' )
        ->and( $provider->exposePluginConfig( 'name' ) )->toBe( 'My Plugin' )
        ->and( $provider->exposePluginConfig( 'nested.key' ) )->toBe( 'value' )
        ->and( $provider->exposePluginConfig( 'missing' ) )->toBeNull();
} );
