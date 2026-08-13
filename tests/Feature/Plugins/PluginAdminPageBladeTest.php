<?php

declare( strict_types=1 );

/**
 * Feature coverage for the Blade flavor of
 * {@see PluginServiceProvider::registerAdminPage()} (issue #246).
 *
 * The documented flow — register a namespaced Blade view, extend the
 * framework's admin layout — was broken at two points: the view name reached
 * `Route::get()` as a route action, and `cms::admin.layouts.app` did not
 * exist. This suite walks the whole path, from registration through an
 * authenticated HTTP request, so neither half can regress silently.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.8.0
 */

use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminMenuManager;
use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminPageManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\PluginServiceProvider;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;

beforeEach( function (): void {
    Gate::before( fn ( ?Illuminate\Contracts\Auth\Authenticatable $user, string $ability ) => true );

    $this->app->singleton( AdminMenuManager::class, fn () => new AdminMenuManager );

    View::addNamespace( 'test-plugin', __DIR__ . '/../../Support/PluginViews' );
} );

it( 'renders a plugin Blade admin page over HTTP', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerAdminPage( 'test-plugin', [
                'title'      => 'Test Plugin',
                'view'       => 'test-plugin::admin.index',
                'capability' => 'access_admin_dashboard',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'test-plugin'];
        }
    };

    $provider->boot();

    app( AdminPageManager::class )->registerRoutes();

    $response = $this->actingAs( TestUser::factory()->create() )->get( '/admin/test-plugin' );

    $response->assertOk()
        ->assertSee( 'Hello from the test plugin' );
} );

it( 'renders the plugin page inside the framework admin layout', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerAdminPage( 'test-plugin', [
                'title'      => 'Test Plugin',
                'view'       => 'test-plugin::admin.index',
                'capability' => 'access_admin_dashboard',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'test-plugin'];
        }
    };

    $provider->boot();

    app( AdminPageManager::class )->registerRoutes();

    $response = $this->actingAs( TestUser::factory()->create() )->get( '/admin/test-plugin' );

    // Layout chrome plus the page's own nav entry, proving the layout ran and
    // rendered the admin menu rather than the page escaping it.
    $response->assertSee( '<title>Test Plugin', false )
        ->assertSee( 'cms-admin__content', false )
        ->assertSee( '/admin/test-plugin', false );
} );

it( 'renders sections, sub-items and route parameters, and hides showInMenu=false rows', function (): void {
    apAddAdminSection( 'tools', 'Tools', 10 );

    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerAdminPage( 'test-plugin', [
                'title'      => 'Test Plugin',
                'section'    => 'tools',
                'view'       => 'test-plugin::admin.index',
                'capability' => 'access_admin_dashboard',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'test-plugin'];
        }
    };

    $provider->boot();

    apAddSubAdminPage( 'Visible Child', 'test-plugin/child', 'test-plugin', [
        'action'     => fn () => 'child',
        'capability' => '',
    ] );
    apAddSubAdminPage( 'Hidden Child', 'test-plugin/hidden', 'test-plugin', [
        'action'     => fn () => 'hidden',
        'capability' => '',
        'showInMenu' => false,
    ] );

    // A filter subscriber is the one path that reaches the renderer without
    // passing through registerNavEntry's ingress check.
    addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ): array {
        $menu['evil'] = [
            'title' => 'Docs',
            'slug'  => 'evil',
            'label' => 'Docs',
            'url'   => "java\tscript:alert(1)",
            'order' => 99,
        ];

        return $menu;
    } );

    app( AdminPageManager::class )->registerRoutes();

    $response = $this->actingAs( TestUser::factory()->create() )->get( '/admin/test-plugin' );

    $response->assertOk()
        ->assertSee( 'cms-admin__section-title', false )
        ->assertSee( 'Tools', false )
        ->assertSee( 'Visible Child', false )
        ->assertDontSee( 'Hidden Child', false )
        ->assertSee( 'href="#"', false )
        ->assertDontSee( 'script:alert', false );
} );

it( 'passes route parameters into the wrapped view', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerAdminPage( 'test-plugin/{reportId}', [
                'title'      => 'Test Plugin Report',
                'view'       => 'test-plugin::admin.report',
                'capability' => 'access_admin_dashboard',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'test-plugin'];
        }
    };

    $provider->boot();

    app( AdminPageManager::class )->registerRoutes();

    $response = $this->actingAs( TestUser::factory()->create() )->get( '/admin/test-plugin/42' );

    $response->assertOk()
        ->assertSee( 'Report 42' );
} );

it( 'registers the cms view namespace with an admin layout', function (): void {
    expect( View::exists( 'cms::admin.layouts.app' ) )->toBeTrue()
        ->and( View::exists( 'cms::admin.partials.menu' ) )->toBeTrue();
} );

it( 'publishes the cms views so a host can replace the layout', function (): void {
    $tags = Illuminate\Support\ServiceProvider::$publishGroups;

    expect( $tags )->toHaveKey( 'cms-views' );

    // Asserted as a pair, not as two independent flags: the tag mapping only
    // does anything useful if THIS source lands at THAT destination. Laravel
    // resolves `resources/views/vendor/{namespace}` ahead of the package's own
    // path, so this destination is what makes an override win without the
    // plugin changing the view it extends.
    $expected    = realpath( __DIR__ . '/../../../resources/views' );
    $destination = null;

    // publishes() keys on the path as written (`src/../resources/views`), so
    // match on the resolved path and then assert THAT entry's destination.
    foreach ( $tags['cms-views'] as $source => $target ) {
        if ( realpath( $source ) === $expected ) {
            $destination = $target;
        }
    }

    expect( $expected )->not->toBeFalse()
        ->and( $destination )->not->toBeNull()
        ->and( $destination )->toEndWith( 'views/vendor/cms' );
} );
