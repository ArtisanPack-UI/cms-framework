<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedEntity;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplateResolver;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    $this->themesPath  = base_path( 'themes' );
    $this->themeSlug   = 'test-theme';
    $this->themeFiles  = $this->themesPath . '/' . $this->themeSlug . '/templates';

    File::ensureDirectoryExists( $this->themeFiles );
    File::put(
        $this->themesPath . '/' . $this->themeSlug . '/theme.json',
        json_encode( [ 'name' => 'Test', 'slug' => $this->themeSlug, 'version' => '1.0.0' ] ),
    );

    config()->set( 'cms.themes.cacheEnabled', false );

    // Stub ThemeManager::getActiveTheme() to return our test theme.
    $themeManager = $this->mock( ThemeManager::class, function ( $mock ): void {
        $mock->shouldReceive( 'getActiveTheme' )->andReturn( [
            'name' => 'Test',
            'slug' => $this->themeSlug,
        ] );
    } );

    $this->resolver = new TemplateResolver( $themeManager );
} );

afterEach( function (): void {
    File::deleteDirectory( $this->themesPath . '/' . $this->themeSlug );
} );

describe( 'TemplateResolver::resolve()', function (): void {
    it( 'returns the theme file when only the file exists', function (): void {
        File::put( $this->themeFiles . '/page.html', '<!-- wp:paragraph --><p>Page</p><!-- /wp:paragraph -->' );

        $result = $this->resolver->resolve( 'page' );

        expect( $result )
            ->toBeInstanceOf( ResolvedEntity::class )
            ->and( $result->source )->toBe( 'theme' )
            ->and( $result->slug )->toBe( 'page' )
            ->and( $result->theme )->toBe( $this->themeSlug )
            ->and( $result->content )->toContain( 'wp:paragraph' )
            ->and( $result->hasThemeFile )->toBeTrue()
            ->and( $result->isCustom )->toBeFalse()
            ->and( $result->wpId() )->toBe( 0 );
    } );

    it( 'returns the DB row when only a DB row exists (custom template)', function (): void {
        $row = Template::create( [
            'theme'         => $this->themeSlug,
            'slug'          => 'custom-page',
            'title'         => 'Custom Page',
            'is_custom'     => true,
            'block_content' => [ [ 'blockName' => 'core/paragraph' ] ],
        ] );

        $result = $this->resolver->resolve( 'custom-page' );

        expect( $result->source )->toBe( 'db' )
            ->and( $result->isCustom )->toBeTrue()
            ->and( $result->hasThemeFile )->toBeFalse()
            ->and( $result->wpId() )->toBe( $row->id );
    } );

    it( 'returns the DB row over the theme file when both exist (DB wins)', function (): void {
        File::put( $this->themeFiles . '/index.html', '<!-- file content -->' );

        $row = Template::create( [
            'theme'         => $this->themeSlug,
            'slug'          => 'index',
            'title'         => 'DB Index',
            'is_custom'     => false,
            'block_content' => [ [ 'blockName' => 'core/heading' ] ],
        ] );

        $result = $this->resolver->resolve( 'index' );

        expect( $result->source )->toBe( 'db' )
            ->and( $result->isCustom )->toBeFalse() // DB row exists but theme file also backs it
            ->and( $result->hasThemeFile )->toBeTrue()
            ->and( $result->wpId() )->toBe( $row->id );
    } );

    it( 'returns null when neither DB nor file has the slug', function (): void {
        expect( $this->resolver->resolve( 'missing' ) )->toBeNull();
    } );
} );

describe( 'TemplateResolver::all()', function (): void {
    it( 'merges file slugs and DB slugs, deduplicating with DB winning', function (): void {
        File::put( $this->themeFiles . '/page.html', '<!-- page from file -->' );
        File::put( $this->themeFiles . '/single.html', '<!-- single from file -->' );

        Template::create( [
            'theme'     => $this->themeSlug,
            'slug'      => 'page',
            'title'     => 'Page From DB',
            'is_custom' => false,
        ] );
        Template::create( [
            'theme'     => $this->themeSlug,
            'slug'      => 'archive',
            'title'     => 'Custom Archive',
            'is_custom' => true,
        ] );

        $all = $this->resolver->all();

        $bySlug = collect( $all )->keyBy( 'slug' );

        expect( $bySlug->keys()->all() )->toEqual( [ 'archive', 'page', 'single' ] )
            ->and( $bySlug->get( 'page' )->source )->toBe( 'db' )
            ->and( $bySlug->get( 'single' )->source )->toBe( 'theme' )
            ->and( $bySlug->get( 'archive' )->source )->toBe( 'db' );
    } );
} );

describe( 'TemplateResolver::revert()', function (): void {
    it( 'deletes the DB row and returns true', function (): void {
        Template::create( [
            'theme' => $this->themeSlug,
            'slug'  => 'page',
            'title' => 'Page',
        ] );

        $result = $this->resolver->revert( 'page' );

        expect( $result )->toBeTrue()
            ->and( Template::where( 'theme', $this->themeSlug )->where( 'slug', 'page' )->exists() )->toBeFalse();
    } );

    it( 'returns false when no DB row exists for the slug', function (): void {
        expect( $this->resolver->revert( 'never-existed' ) )->toBeFalse();
    } );
} );

describe( 'theme isolation', function (): void {
    it( 'does not return a row from a different theme', function (): void {
        Template::create( [
            'theme' => 'other-theme',
            'slug'  => 'page',
            'title' => 'Other Theme Page',
        ] );

        $result = $this->resolver->resolve( 'page' );

        expect( $result )->toBeNull();
    } );
} );
