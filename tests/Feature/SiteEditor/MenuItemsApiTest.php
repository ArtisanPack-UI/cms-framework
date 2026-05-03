<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuItem;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach( function (): void {
    $this->user      = TestUser::factory()->create();
    $this->themeSlug = 'test-theme';

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

describe( 'GET /api/v1/menu-items', function (): void {
    it( 'requires authentication', function (): void {
        $this->getJson( '/api/v1/menu-items' )->assertUnauthorized();
    } );

    it( 'orders results by (parent_id, position)', function (): void {
        $about = MenuItem::create( [
            'menu_id'  => $this->menu->id,
            'position' => 1,
            'type'     => MenuItem::TYPE_SUBMENU,
            'label'    => 'About',
        ] );

        // Created out of order on purpose to verify ordering.
        MenuItem::create( [
            'menu_id'   => $this->menu->id,
            'parent_id' => $about->id,
            'position'  => 0,
            'type'      => MenuItem::TYPE_LINK,
            'label'     => 'Team',
        ] );

        MenuItem::create( [
            'menu_id'  => $this->menu->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_LINK,
            'label'    => 'Home',
            'url'      => '/',
        ] );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/menu-items?menus=' . $this->menu->id );

        $response->assertOk();

        $labels = collect( $response->json() )->pluck( 'title.raw' )->all();

        // Top-level (parent_id=null) sorted to the front by position; then
        // children sorted under by position.
        expect( $labels )->toBe( [ 'Home', 'About', 'Team' ] );
    } );

    it( 'rejects non-numeric ?menus filters', function (): void {
        $this->actingAs( $this->user );

        $this->getJson( '/api/v1/menu-items?menus=abc' )->assertStatus( 422 );
    } );
} );

describe( 'POST /api/v1/menu-items', function (): void {
    it( 'creates a link item', function (): void {
        $this->actingAs( $this->user );

        $response = $this->postJson( '/api/v1/menu-items', [
            'menus' => $this->menu->id,
            'title' => 'Home',
            'url'   => '/',
            'type'  => MenuItem::TYPE_LINK,
        ] );

        $response->assertCreated();
        expect( $response->json( 'title.raw' ) )->toBe( 'Home' )
            ->and( $response->json( 'menus' ) )->toBe( $this->menu->id )
            ->and( $response->json( 'link_type' ) )->toBe( MenuItem::TYPE_LINK );
    } );

    it( 'rejects an invalid link type', function (): void {
        $this->actingAs( $this->user );

        $this->postJson( '/api/v1/menu-items', [
            'menus' => $this->menu->id,
            'title' => 'Home',
            'type'  => 'invalid',
        ] )->assertStatus( 422 );
    } );

    it( 'rejects unpaired (object, object_id) fields', function (): void {
        $this->actingAs( $this->user );

        $this->postJson( '/api/v1/menu-items', [
            'menus'  => $this->menu->id,
            'title'  => 'Post Link',
            'type'   => MenuItem::TYPE_LINK,
            'object' => 'post',
        ] )->assertStatus( 422 );
    } );

    it( 'rejects parent referencing an item in a different menu', function (): void {
        $other = Menu::create( [
            'theme' => $this->themeSlug,
            'slug'  => 'other',
            'name'  => 'Other',
        ] );

        $alien = MenuItem::create( [
            'menu_id'  => $other->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_LINK,
            'label'    => 'Alien',
        ] );

        $this->actingAs( $this->user );

        $this->postJson( '/api/v1/menu-items', [
            'menus'  => $this->menu->id,
            'title'  => 'Bad Parent',
            'type'   => MenuItem::TYPE_LINK,
            'parent' => $alien->id,
        ] )->assertStatus( 422 );
    } );
} );

describe( 'PUT /api/v1/menu-items/{id}', function (): void {
    it( 'updates fields without reassigning the parent menu', function (): void {
        $item = MenuItem::create( [
            'menu_id'  => $this->menu->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_LINK,
            'label'    => 'Old',
            'url'      => '/old',
        ] );

        $this->actingAs( $this->user );

        $response = $this->putJson( '/api/v1/menu-items/' . $item->id, [
            'title' => 'New',
            'url'   => '/new',
        ] );

        $response->assertOk();
        expect( $response->json( 'title.raw' ) )->toBe( 'New' )
            ->and( $response->json( 'url' ) )->toBe( '/new' );
    } );

    it( 'prohibits the menus field on update', function (): void {
        $item = MenuItem::create( [
            'menu_id'  => $this->menu->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_LINK,
            'label'    => 'Item',
        ] );

        $this->actingAs( $this->user );

        $this->putJson( '/api/v1/menu-items/' . $item->id, [
            'menus' => $this->menu->id,
            'title' => 'Item',
        ] )->assertStatus( 422 );
    } );
} );

describe( 'DELETE /api/v1/menu-items/{id}', function (): void {
    it( 'cascades to children', function (): void {
        $parent = MenuItem::create( [
            'menu_id'  => $this->menu->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_SUBMENU,
            'label'    => 'Parent',
        ] );

        $child = MenuItem::create( [
            'menu_id'   => $this->menu->id,
            'parent_id' => $parent->id,
            'position'  => 0,
            'type'      => MenuItem::TYPE_LINK,
            'label'     => 'Child',
        ] );

        $this->actingAs( $this->user );

        $this->deleteJson( '/api/v1/menu-items/' . $parent->id )->assertNoContent();

        expect( MenuItem::query()->find( $parent->id ) )->toBeNull()
            ->and( MenuItem::query()->find( $child->id ) )->toBeNull();
    } );
} );
