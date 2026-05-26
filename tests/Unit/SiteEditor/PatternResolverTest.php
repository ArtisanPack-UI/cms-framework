<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\BlockPattern;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\PatternResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedPattern;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    $this->themesPath = base_path( 'themes' );
    $this->themeSlug  = 'test-theme';
    $this->themeFiles = $this->themesPath . '/' . $this->themeSlug . '/patterns';

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

    $this->resolver = new PatternResolver( $themeManager );
} );

afterEach( function (): void {
    File::deleteDirectory( $this->themesPath . '/' . $this->themeSlug );
} );

describe( 'PatternResolver::resolve()', function (): void {
    it( 'returns a theme pattern from a `.php` file with parsed headers and content', function (): void {
        File::put( $this->themeFiles . '/hero.php', <<<'PHP'
<?php
/**
 * Title: Hero Banner
 * Slug: hero
 * Categories: featured, layout
 * Description: A bold opening section.
 * Block Types: core/post-content
 */
?>
<!-- wp:paragraph --><p>Hero copy</p><!-- /wp:paragraph -->
PHP );

        $result = $this->resolver->resolve( 'hero' );

        expect( $result )->toBeInstanceOf( ResolvedPattern::class )
            ->and( $result->source )->toBe( BlockPattern::SOURCE_THEME )
            ->and( $result->slug )->toBe( 'hero' )
            ->and( $result->userFacingSlug )->toBe( 'hero' )
            ->and( $result->title )->toBe( 'Hero Banner' )
            ->and( $result->description )->toBe( 'A bold opening section.' )
            ->and( $result->categories )->toEqual( ['featured', 'layout'] )
            ->and( $result->blockTypes )->toEqual( ['core/post-content'] )
            ->and( $result->rawContent )->toContain( 'Hero copy' )
            ->and( $result->synced )->toBeFalse()
            ->and( $result->wpId() )->toBe( 0 );
    } );

    it( 'falls back to a humanized slug when the theme file omits the Title header', function (): void {
        File::put( $this->themeFiles . '/no-title.php', <<<'PHP'
<?php
/**
 * Description: No title.
 */
?>
<!-- wp:paragraph --><p>Body</p><!-- /wp:paragraph -->
PHP );

        $result = $this->resolver->resolve( 'no-title' );

        expect( $result->title )->toBe( 'No Title' );
    } );

    it( 'returns a user pattern from the DB and ignores the user/ prefix on lookup', function (): void {
        $row = BlockPattern::create( [
            'slug'          => 'callout',
            'title'         => 'My Callout',
            'source'        => BlockPattern::SOURCE_USER,
            'synced'        => true,
            'block_content' => [['blockName' => 'core/paragraph']],
        ] );

        $byUserFacing = $this->resolver->resolve( 'callout' );
        $byStored     = $this->resolver->resolve( 'user/callout' );

        expect( $byUserFacing->source )->toBe( BlockPattern::SOURCE_USER )
            ->and( $byUserFacing->slug )->toBe( 'user/callout' )
            ->and( $byUserFacing->userFacingSlug )->toBe( 'callout' )
            ->and( $byUserFacing->synced )->toBeTrue()
            ->and( $byUserFacing->blocks )->toBe( [['blockName' => 'core/paragraph']] )
            ->and( $byUserFacing->wpId() )->toBe( $row->id )
            ->and( $byStored->slug )->toBe( $byUserFacing->slug );
    } );

    it( 'returns null when neither DB nor file has the slug', function (): void {
        expect( $this->resolver->resolve( 'missing' ) )->toBeNull();
    } );

    it( 'rejects path-traversal slugs', function (): void {
        File::put( $this->themesPath . '/' . $this->themeSlug . '/secret.php', '<?php /** Title: Secret */ ?>' );

        expect( $this->resolver->resolve( '../secret' ) )->toBeNull();
    } );

    it( 'reads the leading docblock for headers, not a comment buried in the body', function (): void {
        // The leading docblock is the real header; the docblock inside the
        // <style> block is body content that incidentally uses the same
        // doc-comment syntax. Without anchoring, the first non-greedy regex
        // would still pick the leading block, but a file *missing* a leading
        // docblock would fall through and parse the body comment as the
        // header. Cover that explicitly with a no-leading-docblock fixture.
        File::put( $this->themeFiles . '/leading-only.php', <<<'PHP'
<?php
/**
 * Title: Real Hero
 * Categories: featured
 */
?>
<!-- wp:html -->
<style>
/**
 * Title: Imposter
 * Categories: malicious
 */
.x { color: red; }
</style>
<!-- /wp:html -->
PHP );

        $result = $this->resolver->resolve( 'leading-only' );

        expect( $result->title )->toBe( 'Real Hero' )
            ->and( $result->categories )->toEqual( ['featured'] )
            ->and( $result->rawContent )->toContain( 'wp:html' )
            ->and( $result->rawContent )->toContain( 'Imposter' );
    } );

    it( 'does not parse a non-leading docblock as the header when the file has no leading one', function (): void {
        File::put( $this->themeFiles . '/no-header.php', <<<'PHP'
<?php
$variable = 1;
/**
 * Title: Body Comment
 * Categories: not-the-header
 */
?>
<!-- wp:paragraph --><p>Body</p><!-- /wp:paragraph -->
PHP );

        $result = $this->resolver->resolve( 'no-header' );

        // Empty title falls back to humanized slug; categories stay empty —
        // confirms the body docblock is not silently promoted to header.
        expect( $result->title )->toBe( 'No Header' )
            ->and( $result->categories )->toEqual( [] );
    } );
} );

describe( 'PatternResolver::all()', function (): void {
    it( 'merges theme patterns and user patterns under disjoint storage keys', function (): void {
        File::put( $this->themeFiles . '/hero.php', <<<'PHP'
<?php
/**
 * Title: Hero
 */
?>
<!-- hero -->
PHP );

        BlockPattern::create( [
            'slug'   => 'callout',
            'title'  => 'Callout',
            'source' => BlockPattern::SOURCE_USER,
        ] );

        $all = $this->resolver->all();

        expect( array_keys( $all ) )->toEqual( ['hero', 'user/callout'] )
            ->and( $all['hero']->source )->toBe( BlockPattern::SOURCE_THEME )
            ->and( $all['user/callout']->source )->toBe( BlockPattern::SOURCE_USER );
    } );

    it( 'allows a theme pattern and a user pattern to coexist with the same user-facing slug', function (): void {
        File::put( $this->themeFiles . '/hero.php', <<<'PHP'
<?php
/**
 * Title: Theme Hero
 */
?>
<!-- theme -->
PHP );

        BlockPattern::create( [
            'slug'   => 'hero',
            'title'  => 'User Hero',
            'source' => BlockPattern::SOURCE_USER,
        ] );

        $all = $this->resolver->all();

        expect( array_keys( $all ) )->toEqual( ['hero', 'user/hero'] )
            ->and( $all['hero']->title )->toBe( 'Theme Hero' )
            ->and( $all['user/hero']->title )->toBe( 'User Hero' );
    } );
} );

describe( 'PatternResolver::toFilterMap()', function (): void {
    it( 'returns an array shape consumable by visual-editor ResolvedPattern::fromArray', function (): void {
        File::put( $this->themeFiles . '/hero.php', <<<'PHP'
<?php
/**
 * Title: Hero
 * Categories: featured
 * Block Types: core/post-content
 */
?>
<!-- hero -->
PHP );

        $map = $this->resolver->toFilterMap();

        expect( $map )->toHaveKey( 'hero' )
            ->and( $map['hero'] )->toHaveKeys( [
                'slug',
                'title',
                'raw_content',
                'blocks',
                'source',
                'synced',
                'categories',
                'block_types',
                'wp_id',
            ] )
            ->and( $map['hero']['slug'] )->toBe( 'hero' )
            ->and( $map['hero']['source'] )->toBe( BlockPattern::SOURCE_THEME )
            ->and( $map['hero']['synced'] )->toBeFalse();
    } );
} );

describe( 'PatternResolver::cloneToUser()', function (): void {
    it( 'creates a user-source DB row populated from the theme file', function (): void {
        File::put( $this->themeFiles . '/hero.php', <<<'PHP'
<?php
/**
 * Title: Hero
 * Description: From theme.
 * Categories: featured
 */
?>
<!-- hero -->
PHP );

        $row = $this->resolver->cloneToUser( 'hero' );

        expect( $row->source )->toBe( BlockPattern::SOURCE_USER )
            ->and( $row->synced )->toBeFalse()
            ->and( $row->slug )->toBe( 'user/hero' )
            ->and( $row->title )->toBe( 'Hero' )
            ->and( $row->description )->toBe( 'From theme.' )
            ->and( $row->categories )->toEqual( ['featured'] );
    } );

    it( 'throws when the theme pattern does not exist', function (): void {
        $this->resolver->cloneToUser( 'missing' );
    } )->throws( RuntimeException::class );
} );

describe( 'BlockPattern slug prefix', function (): void {
    it( 'auto-prefixes user-source slugs at write time', function (): void {
        $row = BlockPattern::create( [
            'slug'   => 'plain',
            'title'  => 'Plain',
            'source' => BlockPattern::SOURCE_USER,
        ] );

        expect( $row->slug )->toBe( 'user/plain' )
            ->and( $row->userFacingSlug() )->toBe( 'plain' );
    } );

    it( 'is idempotent — accepts an already-prefixed slug without double-prefixing', function (): void {
        $row = BlockPattern::create( [
            'slug'   => 'user/already',
            'title'  => 'Already',
            'source' => BlockPattern::SOURCE_USER,
        ] );

        expect( $row->slug )->toBe( 'user/already' );
    });
});
