<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\ThemeFileBlockParser;
use ArtisanPackUI\CMSFramework\Tests\Support\BlockMarkupHydratorStub;
use Illuminate\Support\Facades\Log;

beforeEach( function (): void {
    $this->markup = '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->';
} );

describe( 'ThemeFileBlockParser::parse() without a hydrator', function (): void {
    it( 'returns an empty tree for blank markup', function (): void {
        expect( ThemeFileBlockParser::parse( '' ) )->toBe( [] )
            ->and( ThemeFileBlockParser::parse( "  \n\t " ) )->toBe( [] );
    } );

    it( 'falls back to the parse_blocks() shape when visual-editor is absent', function (): void {
        // Guards the premise: if visual-editor ever lands in cms-framework's
        // dev deps, this test would silently start exercising the hydrated
        // branch instead of the fallback it is here to cover.
        expect( class_exists( ThemeFileBlockParser::HYDRATOR_CLASS ) )->toBeFalse();

        $blocks = ThemeFileBlockParser::parse( $this->markup );

        expect( $blocks )->toHaveCount( 1 )
            ->and( $blocks[0]['blockName'] )->toBe( 'core/paragraph' )
            ->and( $blocks[0]['innerHTML'] )->toContain( '<p>Hello</p>' );
    } );
} );

describe( 'ThemeFileBlockParser::parse() with a hydrator bound', function (): void {
    it( 'hydrates to the editor shape and passes the markup through verbatim', function (): void {
        $stub = new BlockMarkupHydratorStub();
        app()->instance( ThemeFileBlockParser::HYDRATOR_CLASS, $stub );

        $blocks = ThemeFileBlockParser::parse( $this->markup );

        expect( $blocks )->toBe( [
            [
                'name'        => 'core/paragraph',
                'attributes'  => ['content' => 'hydrated'],
                'innerBlocks' => [],
            ],
        ] )
            ->and( $stub->received )->toBe( [$this->markup] );
    } );

    it( 'does not call the hydrator for blank markup', function (): void {
        $stub = new BlockMarkupHydratorStub();
        app()->instance( ThemeFileBlockParser::HYDRATOR_CLASS, $stub );

        expect( ThemeFileBlockParser::parse( '   ' ) )->toBe( [] )
            ->and( $stub->received )->toBe( [] );
    } );

    it( 'falls back to the parse_blocks() shape and logs when hydration throws', function (): void {
        Log::shouldReceive( 'warning' )
            ->once()
            ->withArgs( fn ( string $message ): bool => str_contains( $message, 'block hydration failed' ) );

        app()->instance(
            ThemeFileBlockParser::HYDRATOR_CLASS,
            new BlockMarkupHydratorStub( shouldThrow: true ),
        );

        $blocks = ThemeFileBlockParser::parse( $this->markup );

        expect( $blocks )->toHaveCount( 1 )
            ->and( $blocks[0]['blockName'] )->toBe( 'core/paragraph' );
    } );

    it( 'ignores a bound value that cannot hydrate', function (): void {
        app()->instance( ThemeFileBlockParser::HYDRATOR_CLASS, new stdClass() );

        $blocks = ThemeFileBlockParser::parse( $this->markup );

        expect( $blocks[0]['blockName'] )->toBe( 'core/paragraph' );
    } );
} );
