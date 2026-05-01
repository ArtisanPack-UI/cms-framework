<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->user        = TestUser::factory()->create();
    $this->themesPath  = base_path( 'themes' );
    $this->themeSlug   = 'test-theme';
    $this->themeFiles  = $this->themesPath . '/' . $this->themeSlug . '/templates';

    File::ensureDirectoryExists( $this->themeFiles );
    File::put(
        $this->themesPath . '/' . $this->themeSlug . '/theme.json',
        json_encode( [ 'name' => 'Test', 'slug' => $this->themeSlug, 'version' => '1.0.0' ] ),
    );

    config()->set( 'cms.themes.cacheEnabled', false );

    // Stub ThemeManager so the controller resolves our test theme as active.
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

describe( 'GET /api/v1/templates', function (): void {
    it( 'requires authentication', function (): void {
        $this->getJson( '/api/v1/templates' )->assertUnauthorized();
    } );

    it( 'returns merged file + DB templates in WP shape', function (): void {
        File::put( $this->themeFiles . '/page.html', '<!-- file -->' );

        Template::create( [
            'theme'     => $this->themeSlug,
            'slug'      => 'archive',
            'title'     => 'Custom Archive',
            'is_custom' => true,
        ] );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/templates' );

        $response->assertOk();
        $response->assertJsonStructure( [
            '*' => [
                'id',
                'slug',
                'theme',
                'type',
                'source',
                'content' => [ 'raw', 'blocks', 'block_version' ],
                'title'   => [ 'raw', 'rendered' ],
                'has_theme_file',
                'is_custom',
                'wp_id',
            ],
        ] );

        $slugs = collect( $response->json() )->pluck( 'slug' )->all();
        expect( $slugs )->toContain( 'page' )->toContain( 'archive' );
    } );

    it( 'always emits id in theme//slug form (even for custom templates)', function (): void {
        Template::create( [
            'theme'     => $this->themeSlug,
            'slug'      => 'custom-only',
            'title'     => 'Custom Only',
            'is_custom' => true,
        ] );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/templates/custom-only' );

        $response->assertOk();
        expect( $response->json( 'id' ) )->toBe( $this->themeSlug . '//custom-only' );
        expect( $response->json( 'wp_id' ) )->toBeInt()->toBeGreaterThan( 0 );
    } );
} );

describe( 'GET /api/v1/templates/{slug}', function (): void {
    it( 'returns the resolved template for a theme-file slug', function (): void {
        File::put( $this->themeFiles . '/single.html', '<!-- wp:heading -->' );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/templates/single' );

        $response->assertOk();
        expect( $response->json( 'source' ) )->toBe( 'theme' );
        expect( $response->json( 'has_theme_file' ) )->toBeTrue();
        expect( $response->json( 'wp_id' ) )->toBe( 0 );
        expect( $response->json( 'content.raw' ) )->toContain( 'wp:heading' );
        expect( $response->json( 'content.blocks' ) )->toBe( [] );
    } );

    it( 'returns DB-stored templates with empty content.raw and populated content.blocks', function (): void {
        Template::create( [
            'theme'         => $this->themeSlug,
            'slug'          => 'db-only',
            'title'         => 'DB Only',
            'is_custom'     => true,
            'block_content' => [ [ 'blockName' => 'core/paragraph' ] ],
        ] );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/templates/db-only' );

        $response->assertOk();
        expect( $response->json( 'content.raw' ) )->toBe( '' );
        expect( $response->json( 'content.blocks' ) )->toBe( [ [ 'blockName' => 'core/paragraph' ] ] );
    } );

    it( '404s when the slug exists in neither file nor DB', function (): void {
        $this->actingAs( $this->user );

        $this->getJson( '/api/v1/templates/missing' )->assertNotFound();
    } );
} );

describe( 'POST /api/v1/templates', function (): void {
    it( 'creates a custom template and returns 201', function (): void {
        $this->actingAs( $this->user );

        $response = $this->postJson( '/api/v1/templates', [
            'slug'          => 'custom-page',
            'title'         => 'Custom Page',
            'is_custom'     => true,
            'block_content' => [ [ 'blockName' => 'core/paragraph' ] ],
        ] );

        $response->assertStatus( 201 );
        expect( $response->json( 'source' ) )->toBe( 'db' );
        expect( $response->json( 'is_custom' ) )->toBeTrue();
        expect( Template::where( 'theme', $this->themeSlug )->where( 'slug', 'custom-page' )->exists() )->toBeTrue();
    } );

    it( 'rejects an invalid slug format', function (): void {
        $this->actingAs( $this->user );

        $this->postJson( '/api/v1/templates', [
            'slug'  => 'Invalid Slug!',
            'title' => 'X',
        ] )->assertStatus( 422 );
    } );

    it( 'returns 409 when posting a duplicate (theme, slug)', function (): void {
        Template::create( [
            'theme' => $this->themeSlug,
            'slug'  => 'page',
            'title' => 'Existing',
        ] );

        $this->actingAs( $this->user );

        $response = $this->postJson( '/api/v1/templates', [
            'slug'  => 'page',
            'title' => 'New',
        ] );

        $response->assertStatus( 409 );
        expect( $response->json( 'errors.slug' ) )->not->toBeEmpty();
    } );
} );

describe( 'PUT slug semantics', function (): void {
    it( 'accepts a body without a slug (route slug is canonical)', function (): void {
        File::put( $this->themeFiles . '/index.html', '<!-- file -->' );

        $this->actingAs( $this->user );

        $this->putJson( '/api/v1/templates/index', [
            'title' => 'DB Index',
        ] )->assertOk();
    } );

    it( 'returns 422 when the body slug does not match the URL slug', function (): void {
        $this->actingAs( $this->user );

        $response = $this->putJson( '/api/v1/templates/index', [
            'slug'  => 'home',
            'title' => 'DB Index',
        ] );

        $response->assertStatus( 422 );
        expect( $response->json( 'errors.slug' ) )->not->toBeEmpty();
    } );
} );

describe( 'PUT /api/v1/templates/{slug}', function (): void {
    it( 'creates an override over a theme-file template', function (): void {
        File::put( $this->themeFiles . '/index.html', '<!-- file -->' );

        $this->actingAs( $this->user );

        $response = $this->putJson( '/api/v1/templates/index', [
            'slug'          => 'index',
            'title'         => 'DB Index',
            'block_content' => [ [ 'blockName' => 'core/heading' ] ],
        ] );

        $response->assertOk();
        expect( $response->json( 'source' ) )->toBe( 'db' );
        expect( $response->json( 'has_theme_file' ) )->toBeTrue();
    } );
} );

describe( 'DELETE /api/v1/templates/{slug}', function (): void {
    it( 'reverts a DB override and returns 204', function (): void {
        Template::create( [
            'theme' => $this->themeSlug,
            'slug'  => 'page',
            'title' => 'Page',
        ] );

        $this->actingAs( $this->user );

        $this->deleteJson( '/api/v1/templates/page' )->assertNoContent();

        expect( Template::where( 'theme', $this->themeSlug )->where( 'slug', 'page' )->exists() )->toBeFalse();
    } );

    it( '404s when no DB row exists for the slug', function (): void {
        $this->actingAs( $this->user );

        $this->deleteJson( '/api/v1/templates/never-existed' )->assertNotFound();
    } );
} );
