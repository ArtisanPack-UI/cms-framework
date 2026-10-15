<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\PatternFileParser;

function patternFileWithViewport( string $header ): string
{
    return "<?php\n/**\n * Title: Hero\n" . $header . " */\n?>\n<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->\n";
}

describe( 'PatternFileParser viewport width', function (): void {
    it( 'parses a numeric Viewport Width header', function (): void {
        $parsed = PatternFileParser::parse( patternFileWithViewport( " * Viewport Width: 1400\n" ) );

        expect( $parsed['viewport_width'] )->toBe( 1400 );
    } );

    it( 'accepts a px suffix', function (): void {
        $parsed = PatternFileParser::parse( patternFileWithViewport( " * Viewport Width: 960px\n" ) );

        expect( $parsed['viewport_width'] )->toBe( 960 );
    } );

    it( 'returns null when the header is missing', function (): void {
        $parsed = PatternFileParser::parse( patternFileWithViewport( '' ) );

        expect( $parsed['viewport_width'] )->toBeNull();
    } );

    it( 'returns null for invalid values', function ( string $value ): void {
        $parsed = PatternFileParser::parse( patternFileWithViewport( " * Viewport Width: {$value}\n" ) );

        expect( $parsed['viewport_width'] )->toBeNull();
    } )->with( [
        'non-numeric' => 'wide',
        'zero'        => '0',
        'negative'    => '-800',
        'decimal'     => '1200.5',
        'empty'       => '',
    ] );

    it( 'clamps out-of-range values', function ( string $value, int $expected ): void {
        $parsed = PatternFileParser::parse( patternFileWithViewport( " * Viewport Width: {$value}\n" ) );

        expect( $parsed['viewport_width'] )->toBe( $expected );
    } )->with( [
        'too narrow' => ['100', PatternFileParser::MIN_VIEWPORT_WIDTH],
        'too wide'   => ['9000', PatternFileParser::MAX_VIEWPORT_WIDTH],
    ] );
} );

describe( 'PatternFileParser WordPress inserter headers (#383)', function (): void {
    it( 'parses Keywords, Template Types and Post Types as lists', function (): void {
        $parsed = PatternFileParser::parse( patternFileWithViewport(
            " * Keywords: hero, banner , call to action\n"
            . " * Template Types: front-page, home\n"
            . " * Post Types: Page, wp_template, page\n",
        ) );

        expect( $parsed['keywords'] )->toBe( ['hero', 'banner', 'call to action'] )
            ->and( $parsed['template_types'] )->toBe( ['front-page', 'home'] )
            ->and( $parsed['post_types'] )->toBe( ['page', 'wp_template'] );
    } );

    it( 'defaults the new headers when they are missing', function (): void {
        $parsed = PatternFileParser::parse( patternFileWithViewport( '' ) );

        expect( $parsed['keywords'] )->toBe( [] )
            ->and( $parsed['inserter'] )->toBeTrue()
            ->and( $parsed['template_types'] )->toBe( [] )
            ->and( $parsed['post_types'] )->toBeNull();
    } );

    it( 'leaves the pattern unscoped when Post Types is empty', function (): void {
        $parsed = PatternFileParser::parse( patternFileWithViewport( " * Post Types:\n" ) );

        expect( $parsed['post_types'] )->toBeNull();
    } );

    it( 'reads the Inserter header the way WordPress does', function ( string $value, bool $expected ): void {
        $parsed = PatternFileParser::parse( patternFileWithViewport( " * Inserter: {$value}\n" ) );

        expect( $parsed['inserter'] )->toBe( $expected );
    } )->with( [
        'no'       => ['no', false],
        'false'    => ['false', false],
        'upper NO' => ['NO', false],
        'other'    => ['hidden', false],
        'yes'      => ['yes', true],
        'true'     => ['TRUE', true],
        'empty'    => ['', true],
    ] );
} );
