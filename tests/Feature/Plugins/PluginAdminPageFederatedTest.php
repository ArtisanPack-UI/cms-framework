<?php

declare( strict_types=1 );

/**
 * Feature coverage for the federated ( React ) flavor of
 * {@see PluginServiceProvider::registerAdminPage()} (issue #296).
 *
 * A `component`-only admin page used to hand its identifier to `Route::get()`
 * as a bare string, which Laravel rejects — and because admin routes register
 * from a `booted()` callback, that took *every* request down, not just the
 * plugin's own page. This suite walks the documented flow end to end: register
 * a `component`, hit its route, and prove the framework renders its mount-point
 * shell inside the admin chrome, and that a host can override the action.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.9.0
 */

use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminMenuManager;
use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminPageManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\PluginServiceProvider;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\Gate;

beforeEach( function (): void {
    Gate::before( fn ( ?Illuminate\Contracts\Auth\Authenticatable $user, string $ability ) => true );

    $this->app->singleton( AdminMenuManager::class, fn () => new AdminMenuManager );

    removeAllFilters( 'ap.cmsFramework.admin.federatedPageAction' );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.cmsFramework.admin.federatedPageAction' );
} );

it( 'renders a federated component admin page inside the admin layout over HTTP', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerAdminPage( 'my-plugin', [
                'title'      => 'My Plugin',
                'component'  => 'myPluginAdmin/Panel',
                'capability' => 'access_admin_dashboard',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'my-plugin'];
        }
    };

    $provider->boot();

    app( AdminPageManager::class )->registerRoutes();

    $response = $this->actingAs( TestUser::factory()->create() )->get( '/admin/my-plugin' );

    // The mount point the host federation runtime hydrates, plus the layout
    // chrome, proving the page renders inside the admin shell rather than as a
    // bare fragment — and that route registration did not throw.
    $response->assertOk()
        ->assertSee( '<title>My Plugin', false )
        ->assertSee( 'cms-admin__content', false )
        ->assertSee( 'cms-admin__federated-fallback', false )
        ->assertSee( 'data-cms-federated-module="myPluginAdmin/Panel"', false );
} );

it( 'renders a visible fallback notice inside the mount when no host hydrates it', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerAdminPage( 'my-plugin', [
                'title'      => 'My Plugin',
                'component'  => 'myPluginAdmin/Panel',
                'capability' => 'access_admin_dashboard',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'my-plugin'];
        }
    };

    $provider->boot();

    app( AdminPageManager::class )->registerRoutes();

    $response = $this->actingAs( TestUser::factory()->create() )->get( '/admin/my-plugin' );

    // Without a host federation runtime, the mount is not a silent dead div:
    // it carries a visible notice naming the module so an operator understands
    // why the page is empty. A host that hydrates the mount replaces this.
    $response->assertOk()
        ->assertSee( 'myPluginAdmin/Panel', false )
        ->assertSee( 'the host application must hydrate', false );
} );

it( 'escapes a malicious component identifier and title in the federated shell', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerAdminPage( 'xss-plugin', [
                'title'      => '<script>alert(1)</script>',
                'component'  => '"><script>alert(1)</script>',
                'capability' => 'access_admin_dashboard',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'xss-plugin'];
        }
    };

    $provider->boot();

    app( AdminPageManager::class )->registerRoutes();

    $response = $this->actingAs( TestUser::factory()->create() )->get( '/admin/xss-plugin' );

    // Neither the component identifier nor the title escapes into raw markup.
    $response->assertOk()
        ->assertDontSee( '<script>alert(1)</script>', false )
        ->assertSee( 'data-cms-federated-module="&quot;&gt;&lt;script&gt;', false )
        ->assertSee( '&lt;script&gt;alert(1)&lt;/script&gt;', false );
} );

it( 'renders a host-overridden federated action over HTTP', function (): void {
    addFilter(
        'ap.cmsFramework.admin.federatedPageAction',
        fn ( Closure $default, string $component, array $config ): Closure =>
            static fn (): string => 'Host mounted ' . $component,
    );

    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerAdminPage( 'my-plugin', [
                'title'      => 'My Plugin',
                'component'  => 'myPluginAdmin/Panel',
                'capability' => 'access_admin_dashboard',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'my-plugin'];
        }
    };

    $provider->boot();

    app( AdminPageManager::class )->registerRoutes();

    $response = $this->actingAs( TestUser::factory()->create() )->get( '/admin/my-plugin' );

    $response->assertOk()
        ->assertSee( 'Host mounted myPluginAdmin/Panel' )
        ->assertDontSee( 'data-cms-federated-module', false );
} );
