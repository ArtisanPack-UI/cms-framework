<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuItem;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuLocationAssignment;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Providers\SiteEditorServiceProvider;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

beforeEach( function (): void {
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

    require_once __DIR__ . '/../../Support/VisualEditorClassStub.php';

    removeAllFilters( 'ap.visual-editor.navigation' );

    (new SiteEditorServiceProvider( app() ))->registerVisualEditorSiteEditorFilters();
} );

afterEach( function (): void {
    removeAllFilters( 'ap.visual-editor.navigation' );
} );

describe( 'ap.visual-editor.navigation filter wiring', function (): void {
    it( 'returns every declared location keyed by location key', function (): void {
        $entries = applyFilters( 'ap.visual-editor.navigation', [] );

        expect( $entries )->toBeArray()
            ->and( array_keys( $entries ) )->toBe( ['primary', 'footer'] )
            ->and( $entries['primary']['wp_id'] )->toBeNull();
    } );

    it( 'fills wp_id and items for assigned locations', function (): void {
        $menu = Menu::create( [
            'theme' => $this->themeSlug,
            'slug'  => 'main',
            'name'  => 'Main',
        ] );

        MenuItem::create( [
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

        $entries = applyFilters( 'ap.visual-editor.navigation', [] );

        expect( $entries['primary']['wp_id'] )->toBe( $menu->id )
            ->and( $entries['primary']['items'] )->toHaveCount( 1 )
            ->and( $entries['primary']['items'][0]['label'] )->toBe( 'Home' );
    } );

    it( 'preserves prior contributors on key collision (static config wins)', function (): void {
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

        $static = [
            'primary' => [
                'location' => 'primary',
                'name'     => 'Static Primary',
                'items'    => [],
                'wp_id'    => 999,
            ],
        ];

        $entries = applyFilters( 'ap.visual-editor.navigation', $static );

        // The cms-framework callback merges its map *under* `$static`, so
        // the static entry wins on collision while still picking up the
        // resolver's `footer` entry.
        expect( $entries['primary']['wp_id'] )->toBe( 999 )
            ->and( $entries )->toHaveKey( 'footer' );
    } );
} );
