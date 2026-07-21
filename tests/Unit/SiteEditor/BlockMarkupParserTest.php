<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\BlockMarkupParser;

describe( 'BlockMarkupParser::parse()', function (): void {
    it( 'returns an empty array for empty or whitespace-only input', function (): void {
        expect( BlockMarkupParser::parse( '' ) )->toBe( [] )
            ->and( BlockMarkupParser::parse( "   \n\t  " ) )->toBe( [] );
    } );

    it( 'parses a single flat block with no attributes', function (): void {
        $markup = '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->';

        $blocks = BlockMarkupParser::parse( $markup );

        expect( $blocks )->toHaveCount( 1 )
            ->and( $blocks[0]['blockName'] )->toBe( 'core/paragraph' )
            ->and( $blocks[0]['attrs'] )->toBe( [] )
            ->and( $blocks[0]['innerBlocks'] )->toBe( [] )
            ->and( $blocks[0]['innerHTML'] )->toBe( '<p>Hello</p>' );
    } );

    it( 'decodes attribute JSON verbatim, including nested objects', function (): void {
        $markup = '<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"32px"}}} --><h2>Hi</h2><!-- /wp:heading -->';

        $blocks = BlockMarkupParser::parse( $markup );

        expect( $blocks[0]['attrs'] )->toBe( [
            'level' => 2,
            'style' => ['typography' => ['fontSize' => '32px']],
        ] );
    } );

    it( 'expands unnamespaced block names to core/*', function (): void {
        $markup = '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->';

        expect( BlockMarkupParser::parse( $markup )[0]['blockName'] )->toBe( 'core/paragraph' );
    } );

    it( 'preserves namespaced block names verbatim', function (): void {
        $markup = '<!-- wp:artisanpack/hero --><div>Hi</div><!-- /wp:artisanpack/hero -->';

        expect( BlockMarkupParser::parse( $markup )[0]['blockName'] )->toBe( 'artisanpack/hero' );
    } );

    it( 'treats self-closing (void) blocks as attribute-only leaves', function (): void {
        $markup = '<!-- wp:site-title /-->';

        $blocks = BlockMarkupParser::parse( $markup );

        expect( $blocks )->toHaveCount( 1 )
            ->and( $blocks[0]['blockName'] )->toBe( 'core/site-title' )
            ->and( $blocks[0]['innerBlocks'] )->toBe( [] )
            ->and( $blocks[0]['innerHTML'] )->toBe( '' )
            ->and( $blocks[0]['innerContent'] )->toBe( [] );
    } );

    it( 'nests innerBlocks under a container opener', function (): void {
        $markup = <<<'HTML'
<!-- wp:group --><div class="wp-block-group">
    <!-- wp:heading --><h2>Title</h2><!-- /wp:heading -->
    <!-- wp:paragraph --><p>Body</p><!-- /wp:paragraph -->
</div><!-- /wp:group -->
HTML;

        $blocks = BlockMarkupParser::parse( $markup );

        expect( $blocks )->toHaveCount( 1 )
            ->and( $blocks[0]['blockName'] )->toBe( 'core/group' )
            ->and( $blocks[0]['innerBlocks'] )->toHaveCount( 2 )
            ->and( $blocks[0]['innerBlocks'][0]['blockName'] )->toBe( 'core/heading' )
            ->and( $blocks[0]['innerBlocks'][0]['innerHTML'] )->toBe( '<h2>Title</h2>' )
            ->and( $blocks[0]['innerBlocks'][1]['blockName'] )->toBe( 'core/paragraph' )
            ->and( $blocks[0]['innerBlocks'][1]['innerHTML'] )->toBe( '<p>Body</p>' );
    } );

    it( 'interleaves freeform slots + null markers on innerContent and derives innerHTML from just the string slots', function (): void {
        // Two invariants share the fixture: the innerContent shape
        // (freeform slots interleaved with one `null` per innerBlock), and
        // innerHTML as the concatenation of only the string slots — matches
        // WP's own contract that innerHTML == implode of the string entries
        // of innerContent.
        $markup = <<<'HTML'
<!-- wp:group --><div>
<!-- wp:heading --><h2>H</h2><!-- /wp:heading -->
</div><!-- /wp:group -->
HTML;

        $blocks = BlockMarkupParser::parse( $markup );

        $slots = $blocks[0]['innerContent'];

        expect( $slots )->toHaveCount( 3 )
            ->and( $slots[0] )->toContain( '<div>' )
            ->and( $slots[1] )->toBeNull()
            ->and( $slots[2] )->toContain( '</div>' )
            ->and( $blocks[0]['innerHTML'] )->toContain( '<div>' )
            ->and( $blocks[0]['innerHTML'] )->toContain( '</div>' )
            ->and( $blocks[0]['innerHTML'] )->not->toContain( '<h2>' );
    } );

    it( 'handles deep three-level nesting', function (): void {
        $markup = <<<'HTML'
<!-- wp:group --><div>
    <!-- wp:columns --><div class="wp-block-columns">
        <!-- wp:column --><div class="wp-block-column">
            <!-- wp:paragraph --><p>Leaf</p><!-- /wp:paragraph -->
        </div><!-- /wp:column -->
    </div><!-- /wp:columns -->
</div><!-- /wp:group -->
HTML;

        $blocks = BlockMarkupParser::parse( $markup );

        expect( $blocks[0]['blockName'] )->toBe( 'core/group' )
            ->and( $blocks[0]['innerBlocks'][0]['blockName'] )->toBe( 'core/columns' )
            ->and( $blocks[0]['innerBlocks'][0]['innerBlocks'][0]['blockName'] )->toBe( 'core/column' )
            ->and( $blocks[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['blockName'] )->toBe( 'core/paragraph' )
            ->and( $blocks[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['innerHTML'] )->toBe( '<p>Leaf</p>' );
    } );

    it( 'returns multiple top-level siblings with indentation stripped between them', function (): void {
        $markup = <<<'HTML'
<!-- wp:heading --><h2>One</h2><!-- /wp:heading -->

<!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->
HTML;

        $blocks = BlockMarkupParser::parse( $markup );

        // No freeform sibling for the blank line between the two — top-level
        // whitespace-only text is dropped so consumers don't see phantom
        // `blockName: null` entries.
        expect( $blocks )->toHaveCount( 2 )
            ->and( $blocks[0]['blockName'] )->toBe( 'core/heading' )
            ->and( $blocks[1]['blockName'] )->toBe( 'core/paragraph' );
    } );

    it( 'emits a blockName-null freeform sibling for real HTML between top-level blocks', function (): void {
        $markup = <<<'HTML'
<!-- wp:heading --><h2>One</h2><!-- /wp:heading -->
<p>Freeform in the middle.</p>
<!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->
HTML;

        $blocks = BlockMarkupParser::parse( $markup );

        expect( $blocks )->toHaveCount( 3 )
            ->and( $blocks[0]['blockName'] )->toBe( 'core/heading' )
            ->and( $blocks[1]['blockName'] )->toBeNull()
            ->and( $blocks[1]['innerHTML'] )->toContain( 'Freeform in the middle.' )
            ->and( $blocks[2]['blockName'] )->toBe( 'core/paragraph' );
    } );

    it( 'degrades a block with malformed attribute JSON to an empty attrs bag rather than dropping it', function (): void {
        // Trailing-comma JSON: brace-balanced (so the regex captures it),
        // but `json_decode` returns null. The block must survive as a
        // real `core/paragraph` with empty attrs — NOT collapse to a
        // freeform sibling. Pinning both keys prevents a future regex
        // tightening from silently switching the degradation path.
        $markup = '<!-- wp:paragraph {"align":"center",} --><p>Body</p><!-- /wp:paragraph -->';

        $blocks = BlockMarkupParser::parse( $markup );

        expect( $blocks )->toHaveCount( 1 )
            ->and( $blocks[0]['blockName'] )->toBe( 'core/paragraph' )
            ->and( $blocks[0]['attrs'] )->toBe( [] )
            ->and( $blocks[0]['innerHTML'] )->toContain( 'Body' );
    } );

    it( 'strips a leading UTF-8 BOM so BOM-prefixed pattern files do not emit a phantom freeform block', function (): void {
        $markup = "\xEF\xBB\xBF<!-- wp:paragraph --><p>body</p><!-- /wp:paragraph -->";

        $blocks = BlockMarkupParser::parse( $markup );

        // Without BOM stripping the first byte-triplet would produce a
        // top-level `blockName: null` sibling; the paragraph must be the
        // only entry.
        expect( $blocks )->toHaveCount( 1 )
            ->and( $blocks[0]['blockName'] )->toBe( 'core/paragraph' );
    } );

    it( 'preserves innerBlocks/innerContent interleave ordering for containers with 2+ children', function (): void {
        // Two innerBlocks with distinct freeform text between them —
        // asserts the `[string, null, string, null, string]` interleave
        // shape that WP contracts on. Without this coverage a regression
        // that pushed all null markers to one end would still pass the
        // single-child interleave case at line 79.
        $markup = <<<'HTML'
<!-- wp:group --><div>
BEFORE
<!-- wp:heading --><h2>H</h2><!-- /wp:heading -->
MIDDLE
<!-- wp:paragraph --><p>P</p><!-- /wp:paragraph -->
AFTER
</div><!-- /wp:group -->
HTML;

        $blocks = BlockMarkupParser::parse( $markup );

        $slots = $blocks[0]['innerContent'];

        // Expected shape: [pre-heading, null, between-heading-and-para, null, post-para]
        expect( $slots )->toHaveCount( 5 )
            ->and( $slots[0] )->toContain( 'BEFORE' )
            ->and( $slots[1] )->toBeNull()
            ->and( $slots[2] )->toContain( 'MIDDLE' )
            ->and( $slots[3] )->toBeNull()
            ->and( $slots[4] )->toContain( 'AFTER' )
            ->and( $blocks[0]['innerBlocks'] )->toHaveCount( 2 )
            ->and( $blocks[0]['innerBlocks'][0]['blockName'] )->toBe( 'core/heading' )
            ->and( $blocks[0]['innerBlocks'][1]['blockName'] )->toBe( 'core/paragraph' );
    } );

    it( 'caps recursion at MAX_DEPTH so an adversarially-nested tree does not blow the PHP call stack', function (): void {
        // 200 nested `wp:group` openers — well past the parser's MAX_DEPTH
        // cap. The exact truncation shape is implementation detail (the
        // capped level flattens its remaining openers into siblings, and
        // unmatched closers at the top level become freeform); the
        // invariant we care about is "parse() returns without raising or
        // exhausting the C stack, and the outer `core/group` is still
        // recognizable as the first result".
        $depth   = 200;
        $openers = str_repeat( '<!-- wp:group --><div>', $depth );
        $closers = str_repeat( '</div><!-- /wp:group -->', $depth );

        $blocks = BlockMarkupParser::parse( $openers . 'leaf' . $closers );

        expect( $blocks )->not->toBe( [] )
            ->and( $blocks[0]['blockName'] )->toBe( 'core/group' );
    } );

    it( 'parses a realistic full theme pattern (group > heading + paragraph + buttons > button)', function (): void {
        $markup = <<<'HTML'
<!-- wp:group {"style":{"spacing":{"padding":{"top":"2rem"}}},"backgroundColor":"primary","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-primary-background-color has-background" style="padding-top:2rem">
    <!-- wp:heading {"level":2,"textAlign":"center"} -->
    <h2 class="has-text-align-center">Try the editor</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"align":"center"} -->
    <p class="has-text-align-center">Demo copy.</p>
    <!-- /wp:paragraph -->

    <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
    <div class="wp-block-buttons">
        <!-- wp:button -->
        <div class="wp-block-button"><a class="wp-block-button__link">Open</a></div>
        <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
</div>
<!-- /wp:group -->
HTML;

        $blocks = BlockMarkupParser::parse( $markup );

        expect( $blocks )->toHaveCount( 1 )
            ->and( $blocks[0]['blockName'] )->toBe( 'core/group' )
            ->and( $blocks[0]['attrs']['backgroundColor'] )->toBe( 'primary' )
            ->and( $blocks[0]['innerBlocks'] )->toHaveCount( 3 )
            ->and( $blocks[0]['innerBlocks'][0]['blockName'] )->toBe( 'core/heading' )
            ->and( $blocks[0]['innerBlocks'][1]['blockName'] )->toBe( 'core/paragraph' )
            ->and( $blocks[0]['innerBlocks'][2]['blockName'] )->toBe( 'core/buttons' )
            ->and( $blocks[0]['innerBlocks'][2]['innerBlocks'][0]['blockName'] )->toBe( 'core/button' );
    } );
} );
