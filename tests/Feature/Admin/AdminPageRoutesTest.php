<?php

declare( strict_types=1 );

/**
 * Coverage for issue #357.
 *
 * Admin pages must sit behind the host's configurable admin middleware stack
 * ( so plugin pages honour `verified` / two-factor like the host's own admin
 * routes ), and their names must resolve when routes are added to a cached
 * ( compiled ) route collection.
 */

use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminPageManager;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

afterEach( function (): void {
    removeAllFilters( 'ap.cmsFramework.admin.middleware' );
} );

/**
 * Registers a single admin page and returns the route found under the expected name.
 */
function registerAdminTestPage( string $slug = 'issue-357-page', string $expectedName = 'admin.issue-357-page' ): ?Illuminate\Routing\Route
{
    app( AdminPageManager::class )->register( $slug, fn (): string => 'ok', 'access_admin_dashboard' );
    app( AdminPageManager::class )->registerRoutes();

    return Route::getRoutes()->getByName( $expectedName );
}

it( 'applies the default web and auth middleware plus the capability gate', function (): void {
    $route = registerAdminTestPage();

    expect( $route )->not->toBeNull()
        ->and( $route->middleware() )->toBe( [ 'web', 'auth', 'can:access_admin_dashboard' ] );
} );

it( 'applies the middleware stack configured by the host', function (): void {
    config()->set( 'cms.admin.middleware', [ 'web', 'auth', 'verified', 'two-factor', 'two-factor.enroll' ] );

    $route = registerAdminTestPage();

    expect( $route->middleware() )->toBe( [
        'web',
        'auth',
        'verified',
        'two-factor',
        'two-factor.enroll',
        'can:access_admin_dashboard',
    ] );
} );

it( 'lets the middleware stack be modified through a filter', function (): void {
    addFilter( 'ap.cmsFramework.admin.middleware', fn ( array $middleware ): array => [ ...$middleware, 'verified' ] );

    $route = registerAdminTestPage();

    expect( $route->middleware() )->toBe( [ 'web', 'auth', 'verified', 'can:access_admin_dashboard' ] );
} );

it( 'falls back to web and auth when the configured stack is empty or invalid', function ( mixed $configured ): void {
    config()->set( 'cms.admin.middleware', $configured );

    $route = registerAdminTestPage();

    expect( $route->middleware() )->toBe( [ 'web', 'auth', 'can:access_admin_dashboard' ] );
} )->with( [
    'empty array'      => [ [] ],
    'null'             => [ null ],
    'non-string items' => [ [ '', null, 42 ] ],
] );

it( 'drops non-string entries from the configured stack', function (): void {
    config()->set( 'cms.admin.middleware', [ 'web', '', null, 'auth', 'verified' ] );

    $route = registerAdminTestPage();

    expect( $route->middleware() )->toBe( [ 'web', 'auth', 'verified', 'can:access_admin_dashboard' ] );
} );

it( 'names nested and parameterised slugs predictably', function (): void {
    $route = registerAdminTestPage( 'issue-357/settings/{tab}', 'admin.issue-357.settings' );

    expect( $route )->not->toBeNull()
        ->and( $route->uri() )->toBe( 'admin/issue-357/settings/{tab}' );
} );

it( 'resolves admin page route names under a cached route collection', function (): void {
    Route::setCompiledRoutes( ( new RouteCollection )->compile() );

    registerAdminTestPage();

    expect( Route::getRoutes()->hasNamedRoute( 'admin.issue-357-page' ) )->toBeTrue()
        ->and( route( 'admin.issue-357-page' ) )->toEndWith( '/admin/issue-357-page' );
} );

it( 'redirects guests to login under the default stack', function (): void {
    Route::get( '/login', fn (): string => 'login' )->name( 'login' );

    registerAdminTestPage();

    $this->get( '/admin/issue-357-page' )->assertRedirect( '/login' );
} );
