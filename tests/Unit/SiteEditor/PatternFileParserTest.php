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
