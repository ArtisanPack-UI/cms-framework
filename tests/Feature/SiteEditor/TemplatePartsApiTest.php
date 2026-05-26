<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\TemplatePart;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->user        = TestUser::factory()->create();
    $this->themesPath  = base_path( 'themes' );
    $this->themeSlug   = 'test-theme';
    $this->themeParts  = $this->themesPath . '/' . $this->themeSlug . '/parts';

    File::ensureDirectoryExists( $this->themeParts );
    File::put(
        $this->themesPath . '/' . $this->themeSlug . '/theme.json',
        json_encode( ['name' => 'Test', 'slug' => $this->themeSlug, 'version' => '1.0.0'] ),
    );

    config()->set( 'cms.themes.cacheEnabled', false );

    $this->mock( ThemeManager::class, function ( $mock ): void {
        $mock->shouldReceive( 'getActiveTheme' )->andReturn( [
            'name' => 'Test',
            'slug' => $this->themeSlug,
        ] );
    } );
} );

afterEach( function (): void {
    File::deleteDirectory( $this->themesPath . '/' . $this->themeSlug );
} );

describe( 'GET /api/v1/template-parts', function (): void {
    it( 'requires authentication', function (): void {
        $this->getJson( '/api/v1/template-parts' )->assertUnauthorized();
    } );

    it( 'returns merged file + DB parts with area set', function (): void {
        File::put( $this->themeParts . '/header.html', '<!-- file header -->' );

        TemplatePart::create( [
            'theme'     => $this->themeSlug,
            'slug'      => 'sidebar-blog',
            'title'     => 'Blog Sidebar',
            'area'      => 'sidebar',
            'is_custom' => true,
        ] );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/template-parts' );

        $response->assertOk();
        $bySlug = collect( $response->json() )->keyBy( 'slug' );

        expect( $bySlug->get( 'header' )['area'] )->toBe( 'header' );
        expect( $bySlug->get( 'sidebar-blog' )['area'] )->toBe( 'sidebar' );
        expect( $bySlug->get( 'header' )['type'] )->toBe( 'wp_template_part' );
    } );
} );

describe( 'POST /api/v1/template-parts', function (): void {
    it( 'creates a part with a valid area', function (): void {
        $this->actingAs( $this->user );

        $response = $this->postJson( '/api/v1/template-parts', [
            'slug'      => 'site-footer',
            'title'     => 'Site Footer',
            'area'      => 'footer',
            'is_custom' => true,
        ] );

        $response->assertCreated();
        expect( TemplatePart::where( 'slug', 'site-footer' )->exists() )->toBeTrue();
    } );

    it( 'rejects an invalid area value', function (): void {
        $this->actingAs( $this->user );

        $this->postJson( '/api/v1/template-parts', [
            'slug'  => 'banner',
            'title' => 'Banner',
            'area'  => 'banner', // not in closed list
        ] )->assertStatus( 422 );
    } );

    it( 'rejects when area is missing', function (): void {
        $this->actingAs( $this->user );

        $this->postJson( '/api/v1/template-parts', [
            'slug'  => 'orphan',
            'title' => 'Orphan',
        ] )->assertStatus( 422 );
    } );

    it( 'returns 409 when posting a duplicate (theme, slug)', function (): void {
        TemplatePart::create( [
            'theme' => $this->themeSlug,
            'slug'  => 'header',
            'title' => 'Existing Header',
            'area'  => 'header',
        ] );

        $this->actingAs( $this->user );

        $response = $this->postJson( '/api/v1/template-parts', [
            'slug'  => 'header',
            'title' => 'New Header',
            'area'  => 'header',
        ] );

        $response->assertStatus( 409 );
        expect( $response->json( 'errors.slug' ) )->not->toBeEmpty();
    } );
} );

describe( 'PUT slug semantics for parts', function (): void {
    it( 'returns 422 when body slug does not match URL slug', function (): void {
        $this->actingAs( $this->user );

        $response = $this->putJson( '/api/v1/template-parts/header', [
            'slug'  => 'sidebar',
            'title' => 'Header',
            'area'  => 'header',
        ] );

        $response->assertStatus( 422 );
        expect( $response->json( 'errors.slug' ) )->not->toBeEmpty();
    } );

    it( 'returns 422 when the URL slug is not canonical kebab-case', function (): void {
        $this->actingAs( $this->user );

        $response = $this->putJson( '/api/v1/template-parts/Invalid_Slug', [
            'title' => 'X',
            'area'  => 'header',
        ] );

        $response->assertStatus( 422 );
        expect( $response->json( 'errors.slug' ) )->not->toBeEmpty();
        expect( TemplatePart::where( 'slug', 'Invalid_Slug' )->exists() )->toBeFalse();
    } );
} );

describe( 'DELETE /api/v1/template-parts/{slug}', function (): void {
    it( 'reverts a DB override and returns 204', function (): void {
        TemplatePart::create( [
            'theme' => $this->themeSlug,
            'slug'  => 'header',
            'title' => 'Header',
            'area'  => 'header',
        ] );

        $this->actingAs( $this->user );

        $this->deleteJson( '/api/v1/template-parts/header' )->assertNoContent();
        expect( TemplatePart::where( 'slug', 'header' )->exists() )->toBeFalse();
    } );
} );

describe( 'theme isolation', function (): void {
    it( 'does not list parts from another theme', function (): void {
        TemplatePart::create( [
            'theme' => 'other-theme',
            'slug'  => 'header',
            'title' => 'Other Header',
            'area'  => 'header',
        ] );

        $this->actingAs( $this->user);

        $response = $this->getJson( '/api/v1/template-parts');
        $slugs    = collect( $response->json())->pluck( 'slug')->all();

        expect( $slugs)->not->toContain( 'header');
    });
});
