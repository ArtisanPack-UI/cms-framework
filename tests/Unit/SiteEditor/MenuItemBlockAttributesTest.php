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
    ] );

    $themeManager = $this->mock( ThemeManager::class, function ( $mock ): void {
        $mock->shouldReceive( 'getActiveTheme' )->andReturn( [
            'name' => 'Test',
            'slug' => $this->themeSlug,
        ] );
    } );

    $this->resolver = new MenuResolver( $themeManager );

    $this->menu = Menu::create( [
        'theme' => $this->themeSlug,
        'slug'  => 'main',
        'name'  => 'Main',
    ] );
} );

it( 'round-trips block_attributes as an array', function (): void {
    $attributes = [
        'artisanpackVisibility' => [
            'screenSize' => [
                'direction'   => 'hide',
                'breakpoints' => ['md'],
            ],
        ],
    ];

    $item = MenuItem::create( [
        'menu_id'          => $this->menu->id,
        'type'             => MenuItem::TYPE_LINK,
        'label'            => 'Contact',
        'url'              => '/contact',
        'block_attributes' => $attributes,
    ] );

    expect( $item->fresh()->block_attributes )->toBe( $attributes );
} );

it( 'defaults block_attributes to null', function (): void {
    $item = MenuItem::create( [
        'menu_id' => $this->menu->id,
        'type'    => MenuItem::TYPE_LINK,
        'label'   => 'Home',
    ] );

    expect( $item->fresh()->block_attributes )->toBeNull();
} );

it( 'exposes block_attributes on the resolved navigation shape', function (): void {
    MenuLocationAssignment::create( [
        'theme'    => $this->themeSlug,
        'location' => 'primary',
        'menu_id'  => $this->menu->id,
    ] );

    MenuItem::create( [
        'menu_id'          => $this->menu->id,
        'position'         => 0,
        'type'             => MenuItem::TYPE_LINK,
        'label'            => 'Contact',
        'block_attributes' => ['artisanpackVisibility' => ['screenSize' => ['direction' => 'hide', 'breakpoints' => ['md']]]],
    ] );

    MenuItem::create( [
        'menu_id'  => $this->menu->id,
        'position' => 1,
        'type'     => MenuItem::TYPE_LINK,
        'label'    => 'Home',
    ] );

    $items = $this->resolver->all()['primary']['items'];

    expect( $items[0]['block_attributes'] )->toBe( ['artisanpackVisibility' => ['screenSize' => ['direction' => 'hide', 'breakpoints' => ['md']]]] )
        ->and( $items[1]['block_attributes'] )->toBeNull();
} );
