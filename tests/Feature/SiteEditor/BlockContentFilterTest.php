<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\BlockPattern;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\TemplatePart;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\PatternResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplatePartResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplateResolver;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Support\Facades\File;

const BLOCK_CONTENT_FILTER = 'ap.cmsFramework.siteEditor.blockContent';

/**
 * A block tree carrying per-block Custom CSS, the shape visual-editor 1.15 stores.
 *
 * @return array<int, array<string, mixed>>
 */
function blockContentWithCustomCss( string $css, string $text = 'Hello' ): array
{
    return [
        [
            'blockName' => 'core/paragraph',
            'attrs'     => [
                'content'            => $text,
                'artisanpackEffects' => ['customCss' => $css],
            ],
        ],
    ];
}

/**
 * Filter callback that strips `artisanpackEffects` from every block and
 * records each call's arguments.
 */
function registerCustomCssStripper( ArrayObject $calls ): void
{
    addFilter( BLOCK_CONTENT_FILTER, function ( array $blocks, ?array $storedBlocks, string $context ) use ( $calls ): array {
        $calls[] = ['stored' => $storedBlocks, 'context' => $context];

        return array_map( static function ( array $block ): array {
            unset( $block['attrs']['artisanpackEffects'] );

            return $block;
        }, $blocks );
    }, 10, 3 );
}

/**
 * Fetch the stored record for an endpoint and route slug.
 */
function storedBlockContentRecord( string $context, string $slug ): ?object
{
    // phpcs:disable ArtisanPackUI.Security.ValidatedSanitizedInput -- test-only lookup by a literal slug, parameter-bound by Eloquent.
    return match ( $context ) {
        'template'      => Template::query()->where( 'slug', $slug )->first(),
        'template-part' => TemplatePart::query()->where( 'slug', $slug )->first(),
        default         => BlockPattern::query()->where( 'slug', BlockPattern::withUserPrefix( $slug ) )->first(),
    };
    // phpcs:enable
}

dataset( 'block content endpoints', [
    'templates'      => ['/api/v1/templates', 'template', []],
    'template parts' => ['/api/v1/template-parts', 'template-part', ['area' => 'header']],
    'synced blocks'  => ['/api/v1/blocks', 'block', []],
    'block patterns' => ['/api/v1/block-patterns/patterns', 'block-pattern', []],
] );

const THEME_FILE_MARKUP = '<!-- wp:paragraph {"artisanpackEffects":{"customCss":".theme { color: teal; }"}} --><p>Theme</p><!-- /wp:paragraph -->';

/**
 * Write a theme file for the slug and return the blocks the resolver shows for it.
 *
 * @return array<int, mixed>
 */
function writeThemeFileFor( string $context, string $themeRoot, string $slug ): array
{
    [$directory, $file, $contents, $resolver] = match ( $context ) {
        'template'      => ['templates', $slug . '.html', THEME_FILE_MARKUP, TemplateResolver::class],
        'template-part' => ['parts', $slug . '.html', THEME_FILE_MARKUP, TemplatePartResolver::class],
        default         => ['patterns', $slug . '.php', "<?php\n/**\n * Title: Theme Hero\n */\n?>\n" . THEME_FILE_MARKUP, PatternResolver::class],
    };

    File::ensureDirectoryExists( $themeRoot . '/' . $directory );
    File::put( $themeRoot . '/' . $directory . '/' . $file, $contents );

    return app( $resolver )->resolve( $slug )->blocks;
}

beforeEach( function (): void {
    $this->user       = grantPermissions( TestUser::factory()->create(), 'manage-themes', 'manage-content' );
    $this->themesPath = base_path( 'themes' );
    $this->themeSlug  = 'test-theme';

    File::ensureDirectoryExists( $this->themesPath . '/' . $this->themeSlug );
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

    removeAllFilters( BLOCK_CONTENT_FILTER );

    $this->actingAs( $this->user );
} );

afterEach( function (): void {
    removeAllFilters( BLOCK_CONTENT_FILTER );
    File::deleteDirectory( $this->themesPath . '/' . $this->themeSlug );
} );

describe( 'ap.cmsFramework.siteEditor.blockContent filter', function (): void {
    it( 'filters block content on create and passes null stored blocks', function ( string $url, string $context, array $extra ): void {
        $calls = new ArrayObject();
        registerCustomCssStripper( $calls );

        $this->postJson( $url, $extra + [
            'slug'          => 'filtered',
            'title'         => 'Filtered',
            'block_content' => blockContentWithCustomCss( 'body { color: red; }' ),
        ] )->assertCreated();

        $record = storedBlockContentRecord( $context, 'filtered' );

        expect( $record->block_content[0]['attrs'] )->toBe( ['content' => 'Hello'] )
            ->and( $calls->getArrayCopy() )->toBe( [['stored' => null, 'context' => $context]] );
    } )->with( 'block content endpoints' );

    it( 'filters block content on update and passes the stored blocks', function ( string $url, string $context, array $extra ): void {
        $stored = blockContentWithCustomCss( '.a { color: blue; }', 'Before' );

        $this->postJson( $url, $extra + [
            'slug'          => 'existing',
            'title'         => 'Existing',
            'block_content' => $stored,
        ] )->assertCreated();

        $calls = new ArrayObject();
        registerCustomCssStripper( $calls );

        $this->putJson( $url . '/existing', $extra + [
            'title'         => 'Existing',
            'block_content' => blockContentWithCustomCss( 'body { display: none; }', 'After' ),
        ] )->assertOk();

        $record = storedBlockContentRecord( $context, 'existing' );

        expect( $record->block_content[0]['attrs'] )->toBe( ['content' => 'After'] )
            ->and( $calls->getArrayCopy() )->toBe( [['stored' => $stored, 'context' => $context]] );
    } )->with( 'block content endpoints' );

    it( 'passes null stored blocks when a PUT creates the record', function ( string $url, string $context, array $extra ): void {
        $calls = new ArrayObject();
        registerCustomCssStripper( $calls );

        $this->putJson( $url . '/upserted', $extra + [
            'title'         => 'Upserted',
            'block_content' => blockContentWithCustomCss( 'body { color: red; }' ),
        ] )->assertOk();

        $record = storedBlockContentRecord( $context, 'upserted' );

        expect( $record->block_content[0]['attrs'] )->toBe( ['content' => 'Hello'] )
            ->and( $calls->getArrayCopy() )->toBe( [['stored' => null, 'context' => $context]] );
    } )->with( 'block content endpoints' );

    it( 'stores block content unchanged when no filter is registered', function ( string $url, string $context, array $extra ): void {
        $created = blockContentWithCustomCss( '.a { color: blue; }', 'Before' );
        $updated = blockContentWithCustomCss( '.b { color: green; }', 'After' );

        $this->postJson( $url, $extra + [
            'slug'          => 'plain',
            'title'         => 'Plain',
            'block_content' => $created,
        ] )->assertCreated();

        expect( storedBlockContentRecord( $context, 'plain' )->block_content )->toBe( $created );

        $this->putJson( $url . '/plain', $extra + [
            'title'         => 'Plain',
            'block_content' => $updated,
        ] )->assertOk();

        expect( storedBlockContentRecord( $context, 'plain' )->block_content )->toBe( $updated );
    } )->with( 'block content endpoints' );

    it( 'keeps the incoming blocks when a callback returns a non-array', function ( string $url, string $context, array $extra ): void {
        addFilter( BLOCK_CONTENT_FILTER, fn (): string => 'not blocks', 10, 3 );

        $blocks = blockContentWithCustomCss( '.a { color: blue; }' );

        $this->postJson( $url, $extra + [
            'slug'          => 'fallback',
            'title'         => 'Fallback',
            'block_content' => $blocks,
        ] )->assertCreated();

        expect( storedBlockContentRecord( $context, 'fallback' )->block_content )->toBe( $blocks );
    } )->with( 'block content endpoints' );

    it( 'does not run the filter when the payload has no block content', function ( string $url, string $context, array $extra ): void {
        $calls = new ArrayObject();
        registerCustomCssStripper( $calls );

        $this->postJson( $url, $extra + [
            'slug'  => 'no-blocks',
            'title' => 'No Blocks',
        ] )->assertCreated();

        $this->putJson( $url . '/no-blocks', $extra + ['title' => 'Renamed'] )->assertOk();

        expect( $calls->count() )->toBe( 0 )
            ->and( storedBlockContentRecord( $context, 'no-blocks' )->title )->toBe( 'Renamed' );
    } )->with( 'block content endpoints' );
} );

describe( 'ap.cmsFramework.siteEditor.blockContent filter on a theme file override', function (): void {
    it( 'passes the theme file blocks as the stored blocks on the first save', function ( string $method, string $url, string $context, array $extra ): void {
        $fileBlocks = writeThemeFileFor( $context, $this->themesPath . '/' . $this->themeSlug, 'hero' );

        expect( json_encode( $fileBlocks ) )->toContain( '.theme { color: teal; }' );

        $calls = new ArrayObject();
        registerCustomCssStripper( $calls );

        $payload = $extra + [
            'title'         => 'Hero',
            'block_content' => blockContentWithCustomCss( 'body { display: none; }', 'Mine' ),
        ];

        if ( 'post' === $method ) {
            $payload['slug'] = 'hero';
        }

        $this->json( strtoupper( $method ), $url, $payload )->assertSuccessful();

        expect( $calls->getArrayCopy() )->toBe( [['stored' => $fileBlocks, 'context' => $context]] )
            ->and( storedBlockContentRecord( $context, 'hero' )->block_content[0]['attrs'] )->toBe( ['content' => 'Mine'] );
    } )->with( [
        'template PUT'        => ['put', '/api/v1/templates/hero', 'template', []],
        'template POST'       => ['post', '/api/v1/templates', 'template', []],
        'template part PUT'   => ['put', '/api/v1/template-parts/hero', 'template-part', ['area' => 'header']],
        'synced block PUT'    => ['put', '/api/v1/blocks/hero', 'block', []],
        'block pattern POST'  => ['post', '/api/v1/block-patterns/patterns', 'block-pattern', []],
    ] );

    it( 'passes the stored row, not the theme file, once an override exists', function (): void {
        writeThemeFileFor( 'template', $this->themesPath . '/' . $this->themeSlug, 'hero' );

        $stored = blockContentWithCustomCss( '.db { color: navy; }', 'Stored' );

        $this->putJson( '/api/v1/templates/hero', ['title' => 'Hero', 'block_content' => $stored] )->assertOk();

        $calls = new ArrayObject();
        registerCustomCssStripper( $calls );

        $this->putJson( '/api/v1/templates/hero', [
            'title'         => 'Hero',
            'block_content' => blockContentWithCustomCss( 'body { display: none; }', 'Mine' ),
        ] )->assertOk();

        expect( $calls->getArrayCopy() )->toBe( [['stored' => $stored, 'context' => 'template']] );
    } );
} );
