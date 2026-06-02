<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuItem;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuLocationAssignment;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach( function (): void {
    $this->user      = TestUser::factory()->create();
    $this->themeSlug = 'test-theme';

    $this->mock( ThemeManager::class, function ( $mock ): void {
        $mock->shouldReceive( 'getActiveTheme' )->andReturn( [
            'name' => 'Test',
            'slug' => $this->themeSlug,
        ] );
    } );
} );

describe( 'GET /api/v1/menus', function (): void {
    it( 'requires authentication', function (): void {
        $this->getJson( '/api/v1/menus' )->assertUnauthorized();
    } );

    it( 'returns menus scoped to the active theme in WP shape', function (): void {
        Menu::create( ['theme' => $this->themeSlug, 'slug' => 'main', 'name' => 'Main'] );
        Menu::create( ['theme' => 'other', 'slug' => 'other-main', 'name' => 'Other'] );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/menus' );

        $response->assertOk();
        $response->assertJsonStructure( ['*' => ['id', 'name', 'slug', 'description', 'meta', 'locations', 'auto_add_pages', 'theme']] );

        $slugs = collect( $response->json() )->pluck( 'slug' )->all();

        expect( $slugs )->toBe( ['main'] );
    } );

    it( 'surfaces assigned locations on each menu', function (): void {
        $menu = Menu::create( ['theme' => $this->themeSlug, 'slug' => 'main', 'name' => 'Main'] );

        MenuLocationAssignment::create( [
            'theme'    => $this->themeSlug,
            'location' => 'primary',
            'menu_id'  => $menu->id,
        ] );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/menus' );

        expect( $response->json( '0.locations' ) )->toBe( ['primary'] );
    } );
} );

describe( 'POST /api/v1/menus', function (): void {
    it( 'creates a menu against the active theme', function (): void {
        $this->actingAs( $this->user );

        $response = $this->postJson( '/api/v1/menus', [
            'slug' => 'main',
            'name' => 'Main',
        ] );

        $response->assertCreated();

        expect( $response->json( 'slug' ) )->toBe( 'main' )
            ->and( $response->json( 'theme' ) )->toBe( $this->themeSlug );

        $this->assertDatabaseHas( 'menus', [
            'theme' => $this->themeSlug,
            'slug'  => 'main',
        ] );
    } );

    it( 'returns 409 on slug collision within the active theme', function (): void {
        Menu::create( ['theme' => $this->themeSlug, 'slug' => 'main', 'name' => 'Existing'] );

        $this->actingAs( $this->user );

        $response = $this->postJson( '/api/v1/menus', [
            'slug' => 'main',
            'name' => 'Duplicate',
        ] );

        $response->assertStatus( 409 );
    } );

    it( 'rejects invalid slugs (non kebab-case)', function (): void {
        $this->actingAs( $this->user );

        $response = $this->postJson( '/api/v1/menus', [
            'slug' => 'Bad_Slug!',
            'name' => 'Bad',
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( ['slug'] );
    } );
} );

describe( 'GET /api/v1/menus/{id_or_slug}', function (): void {
    it( 'shows a menu by integer id', function (): void {
        $menu = Menu::create( ['theme' => $this->themeSlug, 'slug' => 'main', 'name' => 'Main'] );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/menus/' . $menu->id );

        $response->assertOk();
        expect( $response->json( 'id' ) )->toBe( $menu->id );
    } );

    it( 'shows a menu by slug', function (): void {
        Menu::create( ['theme' => $this->themeSlug, 'slug' => 'main', 'name' => 'Main'] );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/menus/main' );

        $response->assertOk();
        expect( $response->json( 'slug' ) )->toBe( 'main' );
    } );

    it( 'returns 404 for menus belonging to other themes', function (): void {
        Menu::create( ['theme' => 'other', 'slug' => 'main', 'name' => 'Other'] );

        $this->actingAs( $this->user );

        $this->getJson( '/api/v1/menus/main' )->assertNotFound();
    } );

    it( 'prefers a slug match over a same-numbered id when both exist', function (): void {
        // Two menus: one whose `id` happens to be 1, one whose `slug` is "1".
        $first = Menu::create( ['theme' => $this->themeSlug, 'slug' => 'first', 'name' => 'First'] );

        $second = Menu::create( ['theme' => $this->themeSlug, 'slug' => (string) $first->id, 'name' => 'Slug Wins'] );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/menus/' . $first->id );

        // Slug match wins — `/menus/1` resolves to the menu with slug "1",
        // not the menu with id 1, so a numeric-only slug stays reachable.
        $response->assertOk();
        expect( $response->json( 'id' ) )->toBe( $second->id )
            ->and( $response->json( 'slug' ) )->toBe( (string) $first->id );
    } );

    it( 'falls back to id lookup when no slug matches', function (): void {
        $menu = Menu::create( ['theme' => $this->themeSlug, 'slug' => 'main', 'name' => 'Main'] );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/menus/' . $menu->id );

        $response->assertOk();
        expect( $response->json( 'id' ) )->toBe( $menu->id );
    } );
} );

describe( 'PUT /api/v1/menus/{id_or_slug}', function (): void {
    it( 'updates menu metadata', function (): void {
        $menu = Menu::create( ['theme' => $this->themeSlug, 'slug' => 'main', 'name' => 'Old Name'] );

        $this->actingAs( $this->user );

        $response = $this->putJson( '/api/v1/menus/' . $menu->id, [
            'name'        => 'New Name',
            'description' => 'Updated',
        ] );

        $response->assertOk();
        expect( $response->json( 'name' ) )->toBe( 'New Name' )
            ->and( $response->json( 'description' ) )->toBe( 'Updated' );
    } );

    it( 'rejects slug renames', function (): void {
        $menu = Menu::create( ['theme' => $this->themeSlug, 'slug' => 'main', 'name' => 'Main'] );

        $this->actingAs( $this->user );

        $this->putJson( '/api/v1/menus/' . $menu->id, [
            'slug' => 'renamed',
            'name' => 'Main',
        ] )->assertStatus( 422 );
    } );
} );

describe( 'DELETE /api/v1/menus/{id_or_slug}', function (): void {
    it( 'cascades to items and location assignments', function (): void {
        $menu = Menu::create( ['theme' => $this->themeSlug, 'slug' => 'main', 'name' => 'Main'] );

        $item = MenuItem::create( [
            'menu_id'  => $menu->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_LINK,
            'label'    => 'Home',
            'url'      => '/',
        ] );

        MenuLocationAssignment::create( [
            'theme'    => $this->themeSlug,
            'location' => 'primary',
            'menu_id'  => $menu->id,
        ] );

        $this->actingAs( $this->user );

        $this->deleteJson( '/api/v1/menus/' . $menu->id )->assertNoContent();

        expect( Menu::query()->find( $menu->id ) )->toBeNull()
            ->and( MenuItem::query()->find( $item->id ) )->toBeNull()
            ->and( MenuLocationAssignment::query()->where( 'menu_id', $menu->id )->exists() )->toBeFalse();
    } );

    it( 'returns 404 for unknown menus', function (): void {
        $this->actingAs( $this->user );

        $this->deleteJson( '/api/v1/menus/9999' )->assertNotFound();
    } );
} );
