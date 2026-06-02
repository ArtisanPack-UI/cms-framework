<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuItem;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuLocationAssignment;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\MenuResolver;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    $this->themeSlug = 'test-theme';

    config()->set( 'cms.menus.locations', [
        'primary' => 'Primary Menu',
        'footer'  => 'Footer Menu',
    ] );

    $themeManager = $this->mock( ThemeManager::class, function ( $mock ): void {
        $mock->shouldReceive( 'getActiveTheme' )->andReturn( [
            'name' => 'Test',
            'slug' => $this->themeSlug,
        ] );
    } );

    $this->resolver = new MenuResolver( $themeManager );
} );

describe( 'MenuResolver::all()', function (): void {
    it( 'returns every declared location, including unassigned, keyed by location', function (): void {
        $resolved = $this->resolver->all();

        expect( $resolved )->toHaveCount( 2 )
            ->and( array_keys( $resolved ) )->toBe( ['primary', 'footer'] );

        expect( $resolved['primary']['wp_id'] )->toBeNull()
            ->and( $resolved['primary']['items'] )->toBe( [] )
            ->and( $resolved['primary']['name'] )->toBe( 'Primary Menu' )
            ->and( $resolved['footer']['wp_id'] )->toBeNull();
    } );

    it( 'fills wp_id and the menu name when a location is assigned', function (): void {
        $menu = Menu::create( [
            'theme' => $this->themeSlug,
            'slug'  => 'main',
            'name'  => 'Main Navigation',
        ] );

        MenuLocationAssignment::create( [
            'theme'    => $this->themeSlug,
            'location' => 'primary',
            'menu_id'  => $menu->id,
        ] );

        $resolved = $this->resolver->all();

        expect( $resolved['primary']['wp_id'] )->toBe( $menu->id )
            ->and( $resolved['primary']['name'] )->toBe( 'Main Navigation' );
    } );

    it( 'projects items into the navigation block shape with hierarchy reconstructed', function (): void {
        $menu = Menu::create( [
            'theme' => $this->themeSlug,
            'slug'  => 'main',
            'name'  => 'Main',
        ] );

        MenuLocationAssignment::create( [
            'theme'    => $this->themeSlug,
            'location' => 'primary',
            'menu_id'  => $menu->id,
        ] );

        $home = MenuItem::create( [
            'menu_id'  => $menu->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_LINK,
            'label'    => 'Home',
            'url'      => '/',
        ] );

        $about = MenuItem::create( [
            'menu_id'  => $menu->id,
            'position' => 1,
            'type'     => MenuItem::TYPE_SUBMENU,
            'label'    => 'About',
        ] );

        MenuItem::create( [
            'menu_id'   => $menu->id,
            'parent_id' => $about->id,
            'position'  => 0,
            'type'      => MenuItem::TYPE_LINK,
            'label'     => 'Team',
            'url'       => '/about/team',
        ] );

        MenuItem::create( [
            'menu_id'   => $menu->id,
            'parent_id' => $about->id,
            'position'  => 1,
            'type'      => MenuItem::TYPE_LINK,
            'label'     => 'Contact',
            'url'       => '/about/contact',
        ] );

        $resolved = $this->resolver->all();

        $items = $resolved['primary']['items'];

        expect( $items )->toHaveCount( 2 )
            ->and( $items[0]['label'] )->toBe( 'Home' )
            ->and( $items[1]['label'] )->toBe( 'About' )
            ->and( $items[1]['type'] )->toBe( MenuItem::TYPE_SUBMENU )
            ->and( $items[1]['children'] )->toHaveCount( 2 )
            ->and( $items[1]['children'][0]['label'] )->toBe( 'Team' )
            ->and( $items[1]['children'][1]['label'] )->toBe( 'Contact' );
    } );

    it( 'flags page-list items as dynamic without enumerating pages', function (): void {
        $menu = Menu::create( [
            'theme' => $this->themeSlug,
            'slug'  => 'main',
            'name'  => 'Main',
        ] );

        MenuLocationAssignment::create( [
            'theme'    => $this->themeSlug,
            'location' => 'primary',
            'menu_id'  => $menu->id,
        ] );

        MenuItem::create( [
            'menu_id'  => $menu->id,
            'position' => 0,
            'type'     => MenuItem::TYPE_PAGE_LIST,
            'label'    => 'Pages',
        ] );

        $resolved = $this->resolver->all();

        $first = $resolved['primary']['items'][0];

        expect( $first['type'] )->toBe( MenuItem::TYPE_PAGE_LIST )
            ->and( $first['dynamic'] )->toBe( MenuItem::TYPE_PAGE_LIST );
    } );

    it( 'isolates assignments by theme — switching themes hides prior theme assignments', function (): void {
        $menu = Menu::create( [
            'theme' => 'other-theme',
            'slug'  => 'main',
            'name'  => 'Main',
        ] );

        MenuLocationAssignment::create( [
            'theme'    => 'other-theme',
            'location' => 'primary',
            'menu_id'  => $menu->id,
        ] );

        $resolved = $this->resolver->all();

        // Active theme is `test-theme`; the `other-theme` assignment must
        // not surface.
        expect( $resolved['primary']['wp_id'] )->toBeNull();
    } );

    it( 'returns an empty array when there is no active theme', function (): void {
        $themeManager = $this->mock( ThemeManager::class, function ( $mock ): void {
            $mock->shouldReceive( 'getActiveTheme' )->andReturn( null );
        } );

        $resolver = new MenuResolver( $themeManager );

        expect( $resolver->all() )->toBe( [] );
    } );
} );

describe( 'MenuResolver::resolve()', function (): void {
    it( 'returns a single resolved location entry', function (): void {
        $entry = $this->resolver->resolve( 'primary' );

        expect( $entry )->toBeArray()
            ->and( $entry['location'] )->toBe( 'primary' );
    } );

    it( 'returns null for an unknown location key', function (): void {
        expect( $this->resolver->resolve( 'sidebar' ) )->toBeNull();
    } );
} );

describe( 'MenuResolver::revert()', function (): void {
    it( 'unassigns a location while preserving the underlying menu', function (): void {
        $menu = Menu::create( [
            'theme' => $this->themeSlug,
            'slug'  => 'main',
            'name'  => 'Main',
        ] );

        MenuLocationAssignment::create( [
            'theme'    => $this->themeSlug,
            'location' => 'primary',
            'menu_id'  => $menu->id,
        ] );

        $reverted = $this->resolver->revert( 'primary' );

        expect( $reverted )->toBeTrue()
            ->and( Menu::query()->find( $menu->id ) )->not->toBeNull()
            ->and( MenuLocationAssignment::query()->where( 'location', 'primary' )->exists() )->toBeFalse();
    } );

    it( 'returns false when no assignment exists', function (): void {
        expect( $this->resolver->revert( 'primary' ) )->toBeFalse();
    } );
});
