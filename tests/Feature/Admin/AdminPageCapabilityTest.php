<?php

declare( strict_types=1 );

/**
 * Coverage for the empty-capability coercion (review item 3.2).
 *
 * An admin page registered with an empty ( or null ) capability must not become
 * an `auth`-only route any authenticated user can reach; it falls back to the
 * `access_admin_dashboard` baseline instead.
 */

use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminPageManager;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

it( 'forbids a user without the baseline capability from an empty-capability admin page', function (): void {
    app( AdminPageManager::class )->register( 'empty-cap-page', fn (): string => 'ok', '' );
    app( AdminPageManager::class )->registerRoutes();

    $this->actingAs( TestUser::factory()->create() )
        ->get( '/admin/empty-cap-page' )
        ->assertForbidden();
} );

it( 'allows a user holding the baseline capability onto an empty-capability admin page', function (): void {
    app( AdminPageManager::class )->register( 'empty-cap-page', fn (): string => 'ok', '' );
    app( AdminPageManager::class )->registerRoutes();

    $user = grantPermissions( TestUser::factory()->create(), 'access_admin_dashboard' );

    $this->actingAs( $user )
        ->get( '/admin/empty-cap-page' )
        ->assertOk()
        ->assertSee( 'ok' );
} );

it( 'applies the same baseline when the capability is null', function (): void {
    app( AdminPageManager::class )->register( 'null-cap-page', fn (): string => 'ok', null );
    app( AdminPageManager::class )->registerRoutes();

    $this->actingAs( TestUser::factory()->create() )
        ->get( '/admin/null-cap-page' )
        ->assertForbidden();
} );
