<?php

declare( strict_types=1 );

/**
 * Authorization + misconfiguration coverage for the federated admin-page flavor
 * (review items: Phase 5 #10 and #11).
 *
 * Unlike PluginAdminPageFederatedTest, this suite does NOT bypass the gate with
 * `Gate::before`, so it exercises the real `can:` middleware the admin routes
 * carry.
 */

use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminMenuManager;
use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminPageManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\PluginServiceProvider;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach( function (): void {
    $this->app->singleton( AdminMenuManager::class, fn () => new AdminMenuManager );

    removeAllFilters( 'ap.cmsFramework.admin.federatedPageAction' );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.cmsFramework.admin.federatedPageAction' );
} );

/**
 * Register a federated admin page through a throwaway provider.
 */
function registerFederatedPage( string $slug, array $config ): void
{
    $provider = new class( app() ) extends PluginServiceProvider {
        public string $pageSlug = '';

        /** @var array<string,mixed> */
        public array $pageConfig = [];

        public function boot(): void
        {
            $this->registerAdminPage( $this->pageSlug, $this->pageConfig );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'federated-security-plugin'];
        }
    };

    $provider->pageSlug   = $slug;
    $provider->pageConfig = $config;
    $provider->boot();

    app( AdminPageManager::class )->registerRoutes();
}

it( 'forbids a capability-less user from a federated admin route', function (): void {
    registerFederatedPage( 'federated-secure', [
        'title'      => 'Federated Secure',
        'component'  => 'secure/Panel',
        'capability' => 'access_admin_dashboard',
    ] );

    $this->actingAs( TestUser::factory()->create() )
        ->get( '/admin/federated-secure' )
        ->assertForbidden();
} );

it( 'allows a user holding the capability onto a federated admin route', function (): void {
    registerFederatedPage( 'federated-secure', [
        'title'      => 'Federated Secure',
        'component'  => 'secure/Panel',
        'capability' => 'access_admin_dashboard',
    ] );

    $user = grantPermissions( TestUser::factory()->create(), 'access_admin_dashboard' );

    $this->actingAs( $user )
        ->get( '/admin/federated-secure' )
        ->assertOk()
        ->assertSee( 'data-cms-federated-module="secure/Panel"', false );
} );

it( 'rejects a guest from a federated admin route', function (): void {
    registerFederatedPage( 'federated-secure', [
        'title'      => 'Federated Secure',
        'component'  => 'secure/Panel',
        'capability' => 'access_admin_dashboard',
    ] );

    // JSON so the auth middleware answers 401 rather than redirecting to a
    // (nonexistent) login route.
    $this->getJson( '/admin/federated-secure' )->assertUnauthorized();
} );

it( 'responds 501 over HTTP for a page declaring neither a view nor a component', function (): void {
    registerFederatedPage( 'unconfigured', [
        'title'      => 'Unconfigured',
        'capability' => 'access_admin_dashboard',
    ] );

    $user = grantPermissions( TestUser::factory()->create(), 'access_admin_dashboard' );

    $this->actingAs( $user )
        ->get( '/admin/unconfigured' )
        ->assertStatus( 501 );
} );
