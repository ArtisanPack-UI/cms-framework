<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuLocationAssignment;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach( function (): void {
    $this->user      = TestUser::factory()->create();
    $this->themeSlug = 'test-theme';

    config()->set( 'cms.menus.locations', [
        'primary' => 'Primary Menu',
        'footer'  => 'Footer Menu',
    ] );

    $this->mock( ThemeManager::class, function ( $mock ): void {
        $mock->shouldReceive( 'getActiveTheme' )->andReturn( [
            'slug' => $this->themeSlug,
        ] );
    } );

    $this->menu = Menu::create( [
        'theme' => $this->themeSlug,
        'slug'  => 'main',
        'name'  => 'Main',
    ] );
} );

describe( 'GET /api/v1/menu-locations', function (): void {
    it( 'requires authentication', function (): void {
        $this->getJson( '/api/v1/menu-locations' )->assertUnauthorized();
    } );

    it( 'lists every declared location with the assigned menu id (or 0 when none)', function (): void {
        MenuLocationAssignment::create( [
            'theme'    => $this->themeSlug,
            'location' => 'primary',
            'menu_id'  => $this->menu->id,
        ] );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/menu-locations' );

        $response->assertOk();
        $response->assertJsonStructure( ['*' => ['name', 'description', 'menu']] );

        $rows = collect( $response->json() )->keyBy( 'name' )->all();

        expect( $rows['primary']['menu'] )->toBe( $this->menu->id )
            ->and( $rows['primary']['description'] )->toBe( 'Primary Menu' )
            ->and( $rows['footer']['menu'] )->toBe( 0 );
    } );
} );

describe( 'PUT /api/v1/menu-locations/{location}', function (): void {
    it( 'assigns a menu to a location', function (): void {
        $this->actingAs( $this->user );

        $response = $this->putJson( '/api/v1/menu-locations/primary', [
            'menu' => $this->menu->id,
        ] );

        $response->assertOk();
        expect( $response->json( 'name' ) )->toBe( 'primary' )
            ->and( $response->json( 'menu' ) )->toBe( $this->menu->id );

        $this->assertDatabaseHas( 'menu_location_assignments', [
            'theme'    => $this->themeSlug,
            'location' => 'primary',
            'menu_id'  => $this->menu->id,
        ] );
    } );

    it( 'rejects assignments to undeclared locations', function (): void {
        $this->actingAs( $this->user );

        $this->putJson( '/api/v1/menu-locations/sidebar', [
            'menu' => $this->menu->id,
        ] )->assertNotFound();
    } );

    it( 'rejects menus authored against another theme', function (): void {
        $other = Menu::create( [
            'theme' => 'other-theme',
            'slug'  => 'other',
            'name'  => 'Other',
        ] );

        $this->actingAs( $this->user );

        $this->putJson( '/api/v1/menu-locations/primary', [
            'menu' => $other->id,
        ] )->assertStatus( 422 );
    } );
} );

describe( 'DELETE /api/v1/menu-locations/{location}', function (): void {
    it( 'unassigns a location', function (): void {
        MenuLocationAssignment::create( [
            'theme'    => $this->themeSlug,
            'location' => 'primary',
            'menu_id'  => $this->menu->id,
        ] );

        $this->actingAs( $this->user );

        $this->deleteJson( '/api/v1/menu-locations/primary' )->assertNoContent();

        $this->assertDatabaseMissing( 'menu_location_assignments', [
            'theme'    => $this->themeSlug,
            'location' => 'primary',
        ] );
    } );

    it( 'returns 404 when nothing was assigned', function (): void {
        $this->actingAs( $this->user );

        $this->deleteJson( '/api/v1/menu-locations/primary' )->assertNotFound();
    } );
});
