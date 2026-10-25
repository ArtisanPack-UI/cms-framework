<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedEntity;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplateResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\ThemeFileBlockParser;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\CMSFramework\Tests\Support\BlockMarkupHydratorStub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    $this->themesPath  = base_path( 'themes' );
    $this->themeSlug   = 'test-theme';
    $this->themeFiles  = $this->themesPath . '/' . $this->themeSlug . '/templates';

    File::ensureDirectoryExists( $this->themeFiles );
    File::put(
        $this->themesPath . '/' . $this->themeSlug . '/theme.json',
        json_encode( ['name' => 'Test', 'slug' => $this->themeSlug, 'version' => '1.0.0'] ),
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
    it( 'returns the theme file with both raw content and a parsed block tree', function (): void {
        File::put( $this->themeFiles . '/page.html', '<!-- wp:paragraph --><p>Page</p><!-- /wp:paragraph -->' );

        $result = $this->resolver->resolve( 'page' );

        expect( $result )
            ->toBeInstanceOf( ResolvedEntity::class )
            ->and( $result->source )->toBe( 'theme' )
            ->and( $result->slug )->toBe( 'page' )
            ->and( $result->theme )->toBe( $this->themeSlug )
            ->and( $result->raw )->toContain( 'wp:paragraph' )
            ->and( $result->blocks )->toHaveCount( 1 )
            ->and( $result->blocks[0]['blockName'] )->toBe( 'core/paragraph' )
            ->and( $result->hasThemeFile )->toBeTrue()
            ->and( $result->isCustom )->toBeFalse()
            ->and( $result->wpId() )->toBe( 0 );
    } );

    it( 'hands theme-file markup to the block hydrator when visual-editor supplies one (#274)', function (): void {
        File::put( $this->themeFiles . '/home.html', '<!-- wp:heading --><h2>Home</h2><!-- /wp:heading -->' );

        $stub = new BlockMarkupHydratorStub();
        app()->instance( ThemeFileBlockParser::HYDRATOR_CLASS, $stub );

        $result = $this->resolver->resolve( 'home' );

        expect( $result->blocks )->toBe( [
            [
                'name'        => 'core/paragraph',
                'attributes'  => ['content' => 'hydrated'],
                'innerBlocks' => [],
            ],
        ] )
            ->and( $stub->received )->toBe( ['<!-- wp:heading --><h2>Home</h2><!-- /wp:heading -->'] );
    } );

    it( 'leaves blocks empty for an empty theme file', function (): void {
        File::put( $this->themeFiles . '/blank.html', '' );

        expect( $this->resolver->resolve( 'blank' )->blocks )->toBe( [] );
    } );

    it( 'populates blocks for every theme-file entity returned by all() (#274)', function (): void {
        File::put( $this->themeFiles . '/one.html', '<!-- wp:paragraph --><p>One</p><!-- /wp:paragraph -->' );
        File::put( $this->themeFiles . '/two.html', '<!-- wp:heading --><h2>Two</h2><!-- /wp:heading -->' );

        $all = collect( $this->resolver->all() )->keyBy( 'slug' );

        expect( $all->get( 'one' )->blocks[0]['blockName'] )->toBe( 'core/paragraph' )
            ->and( $all->get( 'two' )->blocks[0]['blockName'] )->toBe( 'core/heading' );
    } );

    it( 'returns the DB row with blocks populated and raw empty (custom template)', function (): void {
        $row = Template::create( [
            'theme'         => $this->themeSlug,
            'slug'          => 'custom-page',
            'title'         => 'Custom Page',
            'is_custom'     => true,
            'block_content' => [['blockName' => 'core/paragraph']],
        ] );

        $result = $this->resolver->resolve( 'custom-page' );

        expect( $result->source )->toBe( 'db' )
            ->and( $result->isCustom )->toBeTrue()
            ->and( $result->hasThemeFile )->toBeFalse()
            ->and( $result->raw )->toBe( '' )
            ->and( $result->blocks )->toBe( [['blockName' => 'core/paragraph']] )
            ->and( $result->wpId() )->toBe( $row->id );
    } );

    it( 'returns the DB row over the theme file when both exist (DB wins)', function (): void {
        File::put( $this->themeFiles . '/index.html', '<!-- file content -->' );

        $row = Template::create( [
            'theme'         => $this->themeSlug,
            'slug'          => 'index',
            'title'         => 'DB Index',
            'is_custom'     => false,
            'block_content' => [['blockName' => 'core/heading']],
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

describe( 'TemplateResolver Blade fallback (#126)', function (): void {
    it( 'resolves a Blade file when no HTML file exists, marked read-only', function (): void {
        File::put( $this->themeFiles . '/page.blade.php', '<p>{{ $title }}</p>' );

        $result = $this->resolver->resolve( 'page' );

        expect( $result )
            ->toBeInstanceOf( ResolvedEntity::class )
            ->and( $result->source )->toBe( 'theme' )
            ->and( $result->isBlade )->toBeTrue()
            ->and( $result->hasThemeFile )->toBeTrue()
            ->and( $result->isCustom )->toBeFalse()
            ->and( $result->raw )->toBe( '' )
            ->and( $result->blocks )->toBe( [] )
            ->and( $result->title )->toBe( 'Page' );
    } );

    it( 'prefers the HTML file when both HTML and Blade exist (HTML wins)', function (): void {
        File::put( $this->themeFiles . '/page.html', '<!-- wp:paragraph --><p>HTML</p><!-- /wp:paragraph -->' );
        File::put( $this->themeFiles . '/page.blade.php', '<p>{{ $blade }}</p>' );

        $result = $this->resolver->resolve( 'page' );

        expect( $result->isBlade )->toBeFalse()
            ->and( $result->raw )->toContain( 'HTML' )
            ->and( $result->blocks )->toHaveCount( 1 );
    } );

    it( 'lists Blade slugs in all() marked as Blade, deduped against HTML', function (): void {
        File::put( $this->themeFiles . '/blade-only.blade.php', '<p>Blade</p>' );
        File::put( $this->themeFiles . '/both.html', '<!-- html -->' );
        File::put( $this->themeFiles . '/both.blade.php', '<p>blade</p>' );

        $bySlug = collect( $this->resolver->all() )->keyBy( 'slug' );

        expect( $bySlug->keys()->all() )->toEqual( ['blade-only', 'both'] )
            ->and( $bySlug->get( 'blade-only' )->isBlade )->toBeTrue()
            ->and( $bySlug->get( 'both' )->isBlade )->toBeFalse();
    } );

    it( 'lets a DB override win over a Blade file and stay editable', function (): void {
        File::put( $this->themeFiles . '/page.blade.php', '<p>Blade</p>' );

        Template::create( [
            'theme'         => $this->themeSlug,
            'slug'          => 'page',
            'title'         => 'DB Page',
            'is_custom'     => false,
            'block_content' => [['blockName' => 'core/heading']],
        ] );

        $result = $this->resolver->resolve( 'page' );

        expect( $result->source )->toBe( 'db' )
            ->and( $result->isBlade )->toBeFalse()
            ->and( $result->hasThemeFile )->toBeTrue();
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

        expect( $bySlug->keys()->all() )->toEqual( ['archive', 'page', 'single'] )
            ->and( $bySlug->get( 'page' )->source )->toBe( 'db' )
            ->and( $bySlug->get( 'single' )->source )->toBe( 'theme' )
            ->and( $bySlug->get( 'archive' )->source )->toBe( 'db' );
    } );
} );

describe( 'TemplateResolver::all() without per-slug lookups (#424)', function (): void {
    it( 'lists every template with a single templates query', function (): void {
        File::put( $this->themeFiles . '/single.html', '<!-- wp:paragraph --><p>Single</p><!-- /wp:paragraph -->' );
        File::put( $this->themeFiles . '/page.html', '<!-- page -->' );

        foreach ( ['page', 'archive', 'search'] as $slug ) {
            Template::create( ['theme' => $this->themeSlug, 'slug' => $slug, 'title' => ucfirst( $slug )] );
        }

        DB::enableQueryLog();

        $all = $this->resolver->all();

        $queries = collect( DB::getQueryLog() )->filter( fn ( array $query ): bool => str_contains( $query['query'], 'templates' ) );

        expect( $all )->toHaveCount( 4 )
            ->and( $queries )->toHaveCount( 1 );
    } );

    it( 'builds the same entities resolve() returns for each slug', function (): void {
        File::put( $this->themeFiles . '/single.html', '<!-- wp:paragraph --><p>Single</p><!-- /wp:paragraph -->' );
        File::put( $this->themeFiles . '/page.html', '<!-- page -->' );
        File::put( $this->themeFiles . '/legacy.blade.php', '<p>Legacy</p>' );

        Template::create( [
            'theme'         => $this->themeSlug,
            'slug'          => 'page',
            'title'         => 'DB Page',
            'block_content' => [['blockName' => 'core/heading']],
        ] );
        Template::create( ['theme' => $this->themeSlug, 'slug' => 'custom', 'title' => 'Custom', 'is_custom' => true] );

        foreach ( $this->resolver->all() as $entity ) {
            $expected = $this->resolver->resolve( $entity->slug );

            expect( $entity->toFilterEntry() )->toEqual( $expected->toFilterEntry() )
                ->and( $entity->isCustom )->toBe( $expected->isCustom );
        }
    } );

    it( 'includes numeric slugs such as 404 from both the DB and theme files', function (): void {
        File::put( $this->themeFiles . '/404.html', '<!-- wp:paragraph --><p>Missing</p><!-- /wp:paragraph -->' );
        Template::create( ['theme' => $this->themeSlug, 'slug' => '500', 'title' => 'Error'] );

        $bySlug = collect( $this->resolver->all() )->keyBy( 'slug' );

        expect( $bySlug->get( '404' )->source )->toBe( 'theme' )
            ->and( $bySlug->get( '404' )->blocks[0]['blockName'] )->toBe( 'core/paragraph' )
            ->and( $bySlug->get( '500' )->source )->toBe( 'db' );
    } );

    it( 'skips theme files whose names are not valid slugs', function (): void {
        File::put( $this->themeFiles . '/Upper.html', '<!-- upper -->' );
        File::put( $this->themeFiles . '/fine.html', '<!-- fine -->' );

        expect( collect( $this->resolver->all() )->pluck( 'slug' )->all() )->toBe( ['fine'] );
    } );

    it( 'reflects edits to a theme file on the next listing', function (): void {
        File::put( $this->themeFiles . '/page.html', '<!-- wp:paragraph --><p>Before</p><!-- /wp:paragraph -->' );

        $this->resolver->all();

        File::put( $this->themeFiles . '/page.html', '<!-- wp:heading --><h2>After</h2><!-- /wp:heading -->' );

        $page = collect( $this->resolver->all() )->firstWhere( 'slug', 'page' );

        expect( $page->blocks[0]['blockName'] )->toBe( 'core/heading' );
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

    it( 'deletes through the model so the deleted event fires', function (): void {
        $row = Template::create( [
            'theme' => $this->themeSlug,
            'slug'  => 'page',
            'title' => 'Page',
        ] );

        $deletedIds = [];

        Template::deleted( function ( Template $template ) use ( &$deletedIds ): void {
            $deletedIds[] = $template->id;
        } );

        $this->resolver->revert( 'page' );

        expect( $deletedIds )->toBe( [$row->id] );
    } );

    it( 'fires the siteEditor.reverted action with the type, slug and theme', function (): void {
        Template::create( [
            'theme' => $this->themeSlug,
            'slug'  => 'page',
            'title' => 'Page',
        ] );

        $fired = [];

        addAction( 'ap.cmsFramework.siteEditor.reverted', function ( string $type, ?string $slug, string $theme ) use ( &$fired ): void {
            $fired[] = [$type, $slug, $theme];
        } );

        $this->resolver->revert( 'page' );

        expect( $fired )->toBe( [['template', 'page', $this->themeSlug]] );
    } );

    it( 'does not fire the action when there is nothing to revert', function (): void {
        $fired = false;

        addAction( 'ap.cmsFramework.siteEditor.reverted', function () use ( &$fired ): void {
            $fired = true;
        } );

        $this->resolver->revert( 'never-existed' );

        expect( $fired )->toBeFalse();
    } );

    it( 'returns false and keeps the row when a deleting listener cancels the delete', function (): void {
        Template::create( [
            'theme' => $this->themeSlug,
            'slug'  => 'page',
            'title' => 'Page',
        ] );

        Template::deleting( fn (): bool => false );

        $fired = false;

        addAction( 'ap.cmsFramework.siteEditor.reverted', function () use ( &$fired ): void {
            $fired = true;
        } );

        expect( $this->resolver->revert( 'page' ) )->toBeFalse()
            ->and( Template::where( 'slug', 'page' )->exists() )->toBeTrue()
            ->and( $fired )->toBeFalse();
    } );

    it( 'leaves the same slug in another theme untouched', function (): void {
        Template::create( [
            'theme' => 'other-theme',
            'slug'  => 'page',
            'title' => 'Other Theme Page',
        ] );

        expect( $this->resolver->revert( 'page' ) )->toBeFalse()
            ->and( Template::where( 'theme', 'other-theme' )->where( 'slug', 'page' )->exists() )->toBeTrue();
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

describe( 'slug sanitization', function (): void {
    it( 'rejects path-traversal slugs', function (): void {
        // Drop a real file at the would-be traversal target so a missing-file
        // null doesn't mask a successful traversal.
        File::ensureDirectoryExists( $this->themesPath . '/' . $this->themeSlug );
        File::put( $this->themesPath . '/' . $this->themeSlug . '/secret.html', '<!-- secret -->' );

        $result = $this->resolver->resolve( '../secret' );

        expect( $result )->toBeNull();
    } );

    it( 'rejects path-traversal slugs targeting a Blade file outside templates/', function (): void {
        // The Blade fallback resolves `.blade.php` files; make sure the slug
        // guard rejects a traversal to one planted outside the templates dir.
        File::ensureDirectoryExists( $this->themesPath . '/' . $this->themeSlug );
        File::put( $this->themesPath . '/' . $this->themeSlug . '/secret.blade.php', '<p>secret</p>' );

        expect( $this->resolver->resolve( '../secret' ) )->toBeNull();
    } );

    it( 'rejects slugs containing slashes', function (): void {
        expect( $this->resolver->resolve( 'a/b' ) )->toBeNull();
        expect( $this->resolver->resolve( 'a\\b' ) )->toBeNull();
    } );

    it( 'rejects slugs containing null bytes', function (): void {
        expect( $this->resolver->resolve( "page\0evil" ) )->toBeNull();
    } );

    it( 'rejects uppercase slugs', function (): void {
        File::put( $this->themeFiles . '/lowercase.html', '<!-- ok -->' );

        expect( $this->resolver->resolve( 'Lowercase' ) )->toBeNull();
    } );

    it( 'rejects empty slugs', function (): void {
        expect( $this->resolver->resolve( '' ) )->toBeNull();
    } );

    it( 'returns false from revert() for invalid slugs without touching the database', function (): void {
        Template::create( [
            'theme' => $this->themeSlug,
            'slug'  => 'page',
            'title' => 'Page',
        ] );

        expect( $this->resolver->revert( '../page' ) )->toBeFalse()
            ->and( Template::where( 'slug', 'page' )->exists() )->toBeTrue();
    } );
} );
