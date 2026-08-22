<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\TemplatePart;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedEntity;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplatePartResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\ThemeFileBlockParser;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\CMSFramework\Tests\Support\BlockMarkupHydratorStub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    $this->themesPath = base_path( 'themes' );
    $this->themeSlug  = 'test-theme';
    $this->themeFiles = $this->themesPath . '/' . $this->themeSlug . '/parts';

    File::ensureDirectoryExists( $this->themeFiles );
    File::put(
        $this->themesPath . '/' . $this->themeSlug . '/theme.json',
        json_encode( ['name' => 'Test', 'slug' => $this->themeSlug, 'version' => '1.0.0'] ),
    );

    config()->set( 'cms.themes.cacheEnabled', false );

    $themeManager = $this->mock( ThemeManager::class, function ( $mock ): void {
        $mock->shouldReceive( 'getActiveTheme' )->andReturn( [
            'name' => 'Test',
            'slug' => $this->themeSlug,
        ] );
    } );

    $this->resolver = new TemplatePartResolver( $themeManager );
} );

afterEach( function (): void {
    File::deleteDirectory( $this->themesPath . '/' . $this->themeSlug );
} );

describe( 'TemplatePartResolver::resolve()', function (): void {
    it( 'returns the theme file with both raw content and a parsed block tree (#274)', function (): void {
        File::put(
            $this->themeFiles . '/header.html',
            '<!-- wp:group --><!-- wp:site-title /--><!-- /wp:group -->',
        );

        $result = $this->resolver->resolve( 'header' );

        expect( $result )
            ->toBeInstanceOf( ResolvedEntity::class )
            ->and( $result->source )->toBe( 'theme' )
            ->and( $result->area )->toBe( 'header' )
            ->and( $result->raw )->toContain( 'wp:group' )
            ->and( $result->blocks )->toHaveCount( 1 )
            ->and( $result->blocks[0]['blockName'] )->toBe( 'core/group' )
            ->and( $result->blocks[0]['innerBlocks'][0]['blockName'] )->toBe( 'core/site-title' )
            ->and( $result->wpId() )->toBe( 0 );
    } );

    it( 'hands theme-file markup to the block hydrator when visual-editor supplies one (#274)', function (): void {
        File::put( $this->themeFiles . '/footer.html', '<!-- wp:paragraph --><p>Footer</p><!-- /wp:paragraph -->' );

        $stub = new BlockMarkupHydratorStub();
        app()->instance( ThemeFileBlockParser::HYDRATOR_CLASS, $stub );

        $result = $this->resolver->resolve( 'footer' );

        expect( $result->blocks )->toBe( [
            [
                'name'        => 'core/paragraph',
                'attributes'  => ['content' => 'hydrated'],
                'innerBlocks' => [],
            ],
        ] )
            ->and( $stub->received )->toBe( ['<!-- wp:paragraph --><p>Footer</p><!-- /wp:paragraph -->'] );
    } );

    it( 'leaves blocks empty for an empty theme file', function (): void {
        File::put( $this->themeFiles . '/blank.html', '' );

        expect( $this->resolver->resolve( 'blank' )->blocks )->toBe( [] );
    } );

    it( 'returns the DB row over the theme file when both exist (DB wins)', function (): void {
        File::put( $this->themeFiles . '/header.html', '<!-- wp:paragraph --><p>From file</p><!-- /wp:paragraph -->' );

        $row = TemplatePart::create( [
            'theme'         => $this->themeSlug,
            'slug'          => 'header',
            'title'         => 'DB Header',
            'area'          => 'header',
            'is_custom'     => false,
            'block_content' => [['name' => 'core/heading', 'attributes' => [], 'innerBlocks' => []]],
        ] );

        $result = $this->resolver->resolve( 'header' );

        expect( $result->source )->toBe( 'db' )
            ->and( $result->raw )->toBe( '' )
            ->and( $result->blocks )->toBe( [['name' => 'core/heading', 'attributes' => [], 'innerBlocks' => []]] )
            ->and( $result->hasThemeFile )->toBeTrue()
            ->and( $result->wpId() )->toBe( $row->id );
    } );

    it( 'returns null when neither DB nor file has the slug', function (): void {
        expect( $this->resolver->resolve( 'missing' ) )->toBeNull();
    } );

    it( 'rejects path-traversal slugs', function (): void {
        File::put( $this->themesPath . '/' . $this->themeSlug . '/secret.html', '<!-- secret -->' );

        expect( $this->resolver->resolve( '../secret' ) )->toBeNull();
    } );

    it( 'resolves a Blade part when no HTML file exists, marked read-only (#126)', function (): void {
        File::put( $this->themeFiles . '/header.blade.php', '<header>{{ $x }}</header>' );

        $result = $this->resolver->resolve( 'header' );

        expect( $result->source )->toBe( 'theme' )
            ->and( $result->isBlade )->toBeTrue()
            ->and( $result->raw )->toBe( '' )
            ->and( $result->blocks )->toBe( [] )
            ->and( $result->area )->toBe( 'header' );
    } );

    it( 'prefers the HTML part when both HTML and Blade exist (#126)', function (): void {
        File::put( $this->themeFiles . '/footer.html', '<!-- wp:paragraph --><p>F</p><!-- /wp:paragraph -->' );
        File::put( $this->themeFiles . '/footer.blade.php', '<footer>blade</footer>' );

        $result = $this->resolver->resolve( 'footer' );

        expect( $result->isBlade )->toBeFalse()
            ->and( $result->blocks )->toHaveCount( 1 );
    } );
} );

describe( 'TemplatePartResolver::all()', function (): void {
    it( 'populates blocks for every theme-file part (#274)', function (): void {
        File::put( $this->themeFiles . '/header.html', '<!-- wp:paragraph --><p>Head</p><!-- /wp:paragraph -->' );
        File::put( $this->themeFiles . '/footer.html', '<!-- wp:heading --><h2>Foot</h2><!-- /wp:heading -->' );

        $bySlug = collect( $this->resolver->all() )->keyBy( 'slug' );

        expect( $bySlug->get( 'header' )->blocks[0]['blockName'] )->toBe( 'core/paragraph' )
            ->and( $bySlug->get( 'footer' )->blocks[0]['blockName'] )->toBe( 'core/heading' );
    } );
} );
