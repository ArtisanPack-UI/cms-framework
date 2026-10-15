<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\BlockPattern;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\GlobalStyles;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SiteEditorAbilities;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\Gate;

afterEach( function (): void {
    removeAllFilters( 'ap.cmsFramework.abilities.siteEditor.read' );
    removeAllFilters( 'ap.cmsFramework.abilities.siteEditor.structure' );
    removeAllFilters( 'ap.cmsFramework.abilities.siteEditor.content' );
} );

dataset( 'site structure writes', [
    'store template'           => ['postJson', '/api/v1/templates'],
    'update template'          => ['putJson', '/api/v1/templates/home'],
    'delete template'          => ['deleteJson', '/api/v1/templates/home'],
    'store template part'      => ['postJson', '/api/v1/template-parts'],
    'update template part'     => ['putJson', '/api/v1/template-parts/header'],
    'delete template part'     => ['deleteJson', '/api/v1/template-parts/header'],
    'update global styles'     => ['putJson', '/api/v1/global-styles'],
    'delete global styles'     => ['deleteJson', '/api/v1/global-styles'],
    'store menu'               => ['postJson', '/api/v1/menus'],
    'update menu'              => ['putJson', '/api/v1/menus/primary'],
    'delete menu'              => ['deleteJson', '/api/v1/menus/primary'],
    'store menu item'          => ['postJson', '/api/v1/menu-items'],
    'update menu item'         => ['putJson', '/api/v1/menu-items/1'],
    'delete menu item'         => ['deleteJson', '/api/v1/menu-items/1'],
    'assign menu location'     => ['putJson', '/api/v1/menu-locations/primary'],
    'unassign menu location'   => ['deleteJson', '/api/v1/menu-locations/primary'],
] );

dataset( 'site editor reads', [
    'templates'       => ['/api/v1/templates'],
    'template parts'  => ['/api/v1/template-parts'],
    'synced blocks'   => ['/api/v1/blocks'],
    'patterns'        => ['/api/v1/block-patterns/patterns'],
    'global styles'   => ['/api/v1/global-styles'],
    'variations'      => ['/api/v1/global-styles/variations'],
    'menus'           => ['/api/v1/menus'],
    'menu items'      => ['/api/v1/menu-items'],
    'menu locations'  => ['/api/v1/menu-locations'],
] );

dataset( 'site content writes', [
    'store synced block'   => ['postJson', '/api/v1/blocks'],
    'update synced block'  => ['putJson', '/api/v1/blocks/cta'],
    'delete synced block'  => ['deleteJson', '/api/v1/blocks/cta'],
    'store pattern'        => ['postJson', '/api/v1/block-patterns/patterns'],
    'update pattern'       => ['putJson', '/api/v1/block-patterns/patterns/hero'],
    'delete pattern'       => ['deleteJson', '/api/v1/block-patterns/patterns/hero'],
] );

describe( 'Site Editor API authorization', function (): void {
    it( 'forbids a user without manage-themes from writing site structure', function ( string $method, string $uri ): void {
        $this->actingAs( TestUser::factory()->create() );

        $this->{$method}( $uri, [] )->assertForbidden();
    } )->with( 'site structure writes' );

    it( 'forbids a user with only manage-content from writing site structure', function ( string $method, string $uri ): void {
        $this->actingAs( grantPermissions( TestUser::factory()->create(), 'manage-content' ) );

        $this->{$method}( $uri, [] )->assertForbidden();
    } )->with( 'site structure writes' );

    it( 'forbids a user without manage-content from writing patterns and synced blocks', function ( string $method, string $uri ): void {
        $this->actingAs( TestUser::factory()->create() );

        $this->{$method}( $uri, [] )->assertForbidden();
    } )->with( 'site content writes' );

    it( 'requires authentication before authorization', function ( string $method, string $uri ): void {
        $this->{$method}( $uri, [] )->assertUnauthorized();
    } )->with( 'site structure writes' );

    it( 'leaves the data untouched when a write is forbidden', function (): void {
        $template = Template::create( ['theme' => 'test-theme', 'slug' => 'home', 'title' => 'Home', 'is_custom' => true] );
        $menu     = Menu::create( ['theme' => 'test-theme', 'slug' => 'primary', 'name' => 'Primary'] );
        $pattern  = BlockPattern::create( ['slug' => 'hero', 'title' => 'Hero', 'source' => BlockPattern::SOURCE_USER, 'synced' => false] );

        $this->actingAs( TestUser::factory()->create() );

        $this->putJson( '/api/v1/global-styles', ['styles' => ['color' => ['text' => 'transparent']]] )->assertForbidden();
        $this->deleteJson( '/api/v1/templates/home' )->assertForbidden();
        $this->deleteJson( '/api/v1/menus/primary' )->assertForbidden();
        $this->deleteJson( '/api/v1/block-patterns/patterns/hero' )->assertForbidden();

        expect( GlobalStyles::query()->count() )->toBe( 0 )
            ->and( $template->fresh() )->not->toBeNull()
            ->and( $menu->fresh() )->not->toBeNull()
            ->and( $pattern->fresh() )->not->toBeNull();
    } );

    it( 'forbids a user with neither ability from reading', function ( string $uri ): void {
        $this->actingAs( TestUser::factory()->create() );

        $this->getJson( $uri )->assertForbidden();
    } )->with( 'site editor reads' );

    it( 'lets a user with manage-content read', function ( string $uri ): void {
        $this->actingAs( grantPermissions( TestUser::factory()->create(), 'manage-content' ) );

        expect( $this->getJson( $uri )->status() )->not->toBeIn( [401, 403] );
    } )->with( 'site editor reads' );

    it( 'lets a user with only manage-themes read', function ( string $uri ): void {
        $this->actingAs( grantPermissions( TestUser::factory()->create(), 'manage-themes' ) );

        expect( $this->getJson( $uri )->status() )->not->toBeIn( [401, 403] );
    } )->with( 'site editor reads' );

    it( 'requires authentication to read', function ( string $uri ): void {
        $this->getJson( $uri )->assertUnauthorized();
    } )->with( 'site editor reads' );
} );

describe( 'SiteEditorAbilities', function (): void {
    it( 'defaults site structure to manage-themes and content to manage-content', function (): void {
        expect( SiteEditorAbilities::for( SiteEditorAbilities::STRUCTURE ) )->toBe( 'manage-themes' )
            ->and( SiteEditorAbilities::for( SiteEditorAbilities::CONTENT ) )->toBe( 'manage-content' );
    } );

    it( 'defaults reads to manage-content', function (): void {
        expect( SiteEditorAbilities::for( SiteEditorAbilities::READ ) )->toBe( 'manage-content' );
    } );

    it( 'resolves an unknown group as site structure', function (): void {
        expect( SiteEditorAbilities::for( 'nonsense' ) )->toBe( 'manage-themes' );
    } );

    it( 'denies a guest', function (): void {
        expect( SiteEditorAbilities::allows( null, SiteEditorAbilities::READ ) )->toBeFalse();
    } );

    it( 'lets a host remap the read ability', function (): void {
        $viewer = TestUser::factory()->create();

        Gate::define( 'view-site', fn ( $user ): bool => $user->is( $viewer ) );
        addFilter( 'ap.cmsFramework.abilities.siteEditor.read', fn (): string => 'view-site' );

        $this->actingAs( grantPermissions( TestUser::factory()->create(), 'manage-content' ) );
        $this->getJson( '/api/v1/templates' )->assertForbidden();

        $this->actingAs( $viewer );
        $this->getJson( '/api/v1/templates' )->assertOk();
    } );

    it( 'lets a host remap the site structure ability', function (): void {
        $siteEditor = TestUser::factory()->create();

        Gate::define( 'edit-site', fn ( $user ): bool => $user->is( $siteEditor ) );
        addFilter( 'ap.cmsFramework.abilities.siteEditor.structure', fn (): string => 'edit-site' );

        $this->actingAs( grantPermissions( TestUser::factory()->create(), 'manage-themes' ) );
        $this->putJson( '/api/v1/menu-locations/primary', [] )->assertForbidden();

        $this->actingAs( $siteEditor );
        $this->putJson( '/api/v1/menu-locations/primary', [] )->assertUnprocessable();
    } );

    it( 'lets a host remap the content ability', function (): void {
        $patternEditor = TestUser::factory()->create();

        Gate::define( 'manage-patterns', fn ( $user ): bool => $user->is( $patternEditor ) );
        addFilter( 'ap.cmsFramework.abilities.siteEditor.content', fn (): string => 'manage-patterns' );
        addFilter( 'ap.cmsFramework.abilities.siteEditor.read', fn (): string => 'manage-patterns' );

        $this->actingAs( grantPermissions( TestUser::factory()->create(), 'manage-content' ) );
        $this->deleteJson( '/api/v1/block-patterns/patterns/hero' )->assertForbidden();

        $this->actingAs( $patternEditor );
        $this->deleteJson( '/api/v1/block-patterns/patterns/hero' )->assertNotFound();
    } );
} );
