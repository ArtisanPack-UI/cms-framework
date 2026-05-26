<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\BlockPattern;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->user          = TestUser::factory()->create();
    $this->themesPath    = base_path( 'themes' );
    $this->themeSlug     = 'test-theme';
    $this->themePatterns = $this->themesPath . '/' . $this->themeSlug . '/patterns';

    File::ensureDirectoryExists( $this->themePatterns );
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

describe( 'GET /api/v1/blocks (synced patterns)', function (): void {
    it( 'requires authentication', function (): void {
        $this->getJson( '/api/v1/blocks' )->assertUnauthorized();
    } );

    it( 'returns only synced user patterns in wp_block shape', function (): void {
        BlockPattern::create( ['slug' => 'callout', 'title' => 'Callout', 'source' => BlockPattern::SOURCE_USER, 'synced' => true] );
        BlockPattern::create( ['slug' => 'plain', 'title' => 'Plain', 'source' => BlockPattern::SOURCE_USER, 'synced' => false] );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/blocks' );

        $response->assertOk();
        $response->assertJsonStructure( [
            '*' => [
                'id',
                'slug',
                'title'   => ['raw', 'rendered'],
                'content' => ['raw', 'blocks', 'block_version'],
                'description',
                'status',
                'type',
            ],
        ] );

        $slugs = collect( $response->json() )->pluck( 'slug' )->all();
        expect( $slugs )->toEqual( ['callout'] );
    } );

    it( 'shows a single synced user pattern by user-facing slug', function (): void {
        BlockPattern::create( ['slug' => 'callout', 'title' => 'Callout', 'source' => BlockPattern::SOURCE_USER, 'synced' => true] );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/blocks/callout' );

        $response->assertOk();
        expect( $response->json( 'slug' ) )->toBe( 'callout' );
        expect( $response->json( 'type' ) )->toBe( 'wp_block' );
    } );

    it( '404s when looking up an unsynced or theme slug under /blocks', function (): void {
        File::put( $this->themePatterns . '/hero.php', "<?php\n/**\n * Title: Hero\n */\n" );
        BlockPattern::create( ['slug' => 'plain', 'title' => 'Plain', 'source' => BlockPattern::SOURCE_USER, 'synced' => false] );

        $this->actingAs( $this->user );

        $this->getJson( '/api/v1/blocks/hero' )->assertNotFound();
        $this->getJson( '/api/v1/blocks/plain' )->assertNotFound();
    } );
} );

describe( 'POST /api/v1/blocks', function (): void {
    it( 'creates a synced user pattern and returns 201', function (): void {
        $this->actingAs( $this->user );

        $response = $this->postJson( '/api/v1/blocks', [
            'slug'          => 'hero',
            'title'         => 'Hero',
            'block_content' => [['blockName' => 'core/heading']],
        ] );

        $response->assertStatus( 201 );
        expect( $response->json( 'slug' ) )->toBe( 'hero' );
        expect(
            BlockPattern::where( 'slug', 'user/hero' )->where( 'synced', true )->exists(),
        )->toBeTrue();
    } );

    it( 'rejects invalid slugs', function (): void {
        $this->actingAs( $this->user );

        $this->postJson( '/api/v1/blocks', ['slug' => 'Bad Slug!', 'title' => 'X'] )->assertStatus( 422 );
    } );

    it( 'returns 409 on duplicate slug', function (): void {
        BlockPattern::create( ['slug' => 'dup', 'title' => 'Dup', 'source' => BlockPattern::SOURCE_USER, 'synced' => true] );

        $this->actingAs( $this->user );

        $this->postJson( '/api/v1/blocks', ['slug' => 'dup', 'title' => 'Other'] )->assertStatus( 409 );
    } );
} );

describe( 'PUT /api/v1/blocks/{slug}', function (): void {
    it( 'creates the row when one does not exist (upsert)', function (): void {
        $this->actingAs( $this->user );

        $this->putJson( '/api/v1/blocks/new-block', ['title' => 'New Block'] )->assertOk();

        expect( BlockPattern::where( 'slug', 'user/new-block' )->where( 'synced', true )->exists() )->toBeTrue();
    } );

    it( 'returns 422 when body slug does not match URL slug', function (): void {
        $this->actingAs( $this->user );

        $this->putJson( '/api/v1/blocks/foo', ['slug' => 'bar', 'title' => 'X'] )->assertStatus( 422 );
    } );
} );

describe( 'DELETE /api/v1/blocks/{slug}', function (): void {
    it( 'deletes the synced user pattern and returns 204', function (): void {
        BlockPattern::create( ['slug' => 'gone', 'title' => 'Gone', 'source' => BlockPattern::SOURCE_USER, 'synced' => true] );

        $this->actingAs( $this->user );

        $this->deleteJson( '/api/v1/blocks/gone' )->assertNoContent();

        expect( BlockPattern::where( 'slug', 'user/gone' )->exists() )->toBeFalse();
    } );

    it( '404s when no synced row exists', function (): void {
        $this->actingAs( $this->user );

        $this->deleteJson( '/api/v1/blocks/missing' )->assertNotFound();
    } );
} );

describe( 'GET /api/v1/block-patterns/patterns', function (): void {
    it( 'lists theme + user-source unsynced patterns merged', function (): void {
        File::put( $this->themePatterns . '/hero.php', "<?php\n/**\n * Title: Theme Hero\n */\n" );
        BlockPattern::create( ['slug' => 'callout', 'title' => 'Callout', 'source' => BlockPattern::SOURCE_USER, 'synced' => false] );
        BlockPattern::create( ['slug' => 'synced-only', 'title' => 'Synced', 'source' => BlockPattern::SOURCE_USER, 'synced' => true] );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/block-patterns/patterns' );

        $response->assertOk();
        $names = collect( $response->json() )->pluck( 'name' )->sort()->values()->all();
        expect( $names )->toEqual( ['hero', 'user/callout'] );
    } );

    it( 'shows a single theme pattern with source=theme', function (): void {
        File::put( $this->themePatterns . '/hero.php', "<?php\n/**\n * Title: Hero\n * Categories: featured\n */\n" );

        $this->actingAs( $this->user );

        $response = $this->getJson( '/api/v1/block-patterns/patterns/hero' );

        $response->assertOk();
        expect( $response->json( 'source' ) )->toBe( BlockPattern::SOURCE_THEME );
        expect( $response->json( 'wp_id' ) )->toBeNull();
        expect( $response->json( 'categories' ) )->toEqual( ['featured'] );
    } );
} );

describe( 'POST /api/v1/block-patterns/patterns', function (): void {
    it( 'creates an unsynced user pattern with synced=false enforced', function (): void {
        $this->actingAs( $this->user );

        $response = $this->postJson( '/api/v1/block-patterns/patterns', [
            'slug'   => 'new-pattern',
            'title'  => 'New Pattern',
            'synced' => true, // Should be ignored / forced to false
        ] );

        $response->assertStatus( 201 );
        expect(
            BlockPattern::where( 'slug', 'user/new-pattern' )->where( 'synced', false )->exists(),
        )->toBeTrue();
    } );
} );

describe( 'PUT /api/v1/block-patterns/patterns/{slug}', function (): void {
    it( '403s when targeting a theme pattern', function (): void {
        File::put( $this->themePatterns . '/hero.php', "<?php\n/**\n * Title: Hero\n */\n" );

        $this->actingAs( $this->user );

        $this->putJson( '/api/v1/block-patterns/patterns/hero', ['title' => 'New'] )->assertForbidden();
    } );

    it( 'updates an existing user pattern', function (): void {
        BlockPattern::create( ['slug' => 'callout', 'title' => 'Old', 'source' => BlockPattern::SOURCE_USER, 'synced' => false] );

        $this->actingAs( $this->user );

        $this->putJson( '/api/v1/block-patterns/patterns/callout', ['title' => 'New Title'] )->assertOk();

        expect( BlockPattern::where( 'slug', 'user/callout' )->first()->title )->toBe( 'New Title' );
    } );
} );

describe( 'DELETE /api/v1/block-patterns/patterns/{slug}', function (): void {
    it( '403s on a theme pattern', function (): void {
        File::put( $this->themePatterns . '/hero.php', "<?php\n/**\n * Title: Hero\n */\n" );

        $this->actingAs( $this->user );

        $this->deleteJson( '/api/v1/block-patterns/patterns/hero' )->assertForbidden();
    } );

    it( 'deletes a user pattern', function (): void {
        BlockPattern::create( ['slug' => 'gone', 'title' => 'Gone', 'source' => BlockPattern::SOURCE_USER, 'synced' => false] );

        $this->actingAs( $this->user );

        $this->deleteJson( '/api/v1/block-patterns/patterns/gone' )->assertNoContent();

        expect( BlockPattern::where( 'slug', 'user/gone' )->exists() )->toBeFalse();
    } );
} );

describe( 'ap.visual-editor.patterns filter wiring', function (): void {
    beforeEach( function (): void {
        // The filter is registered conditionally on `class_exists(VisualEditor::class)`
        // at SiteEditorServiceProvider::boot(). visual-editor isn't a dev dep of
        // cms-framework, so the gate is false at boot. Loading the stub + manually
        // re-triggering matches how `VisualEditorBridgeTest` exercises the same
        // pattern on the parent CMSFrameworkServiceProvider.
        require_once __DIR__ . '/../../Support/VisualEditorClassStub.php';

        removeAllFilters( 'ap.visual-editor.patterns' );

        (new ArtisanPackUI\CMSFramework\Modules\SiteEditor\Providers\SiteEditorServiceProvider( app() ))
            ->registerVisualEditorSiteEditorFilters();
    } );

    afterEach( function (): void {
        removeAllFilters( 'ap.visual-editor.patterns' );
    } );

    it( 'merges resolved patterns into the filter map under expected storage-form keys', function (): void {
        File::put( $this->themePatterns . '/hero.php', "<?php\n/**\n * Title: Hero\n * Block Types: core/post-content\n */\n" );
        BlockPattern::create( ['slug' => 'callout', 'title' => 'Callout', 'source' => BlockPattern::SOURCE_USER, 'synced' => true] );

        $merged = applyFilters( 'ap.visual-editor.patterns', [] );

        expect( $merged )->toBeArray()
            ->and( $merged )->toHaveKey( 'hero' )
            ->and( $merged )->toHaveKey( 'user/callout' );

        // Shape contract that visual-editor's ResolvedPattern::fromArray expects.
        expect( $merged['hero'] )->toHaveKeys( ['slug', 'title', 'raw_content', 'blocks', 'source', 'synced', 'categories', 'block_types', 'wp_id'] )
            ->and( $merged['hero']['source'] )->toBe( 'theme' )
            ->and( $merged['user/callout']['source'] )->toBe( 'user' )
            ->and( $merged['user/callout']['synced'] )->toBeTrue();
    } );

    it( 'lets static config / earlier contributors win on key collision', function (): void {
        File::put( $this->themePatterns . '/hero.php', "<?php\n/**\n * Title: Theme Hero\n */\n" );

        $existing = [
            'hero' => ['slug' => 'hero', 'title' => 'Static Hero', 'source' => 'theme'],
        ];

        $merged = applyFilters( 'ap.visual-editor.patterns', $existing );

        expect( $merged['hero']['title'] )->toBe( 'Static Hero' );
    });
});
