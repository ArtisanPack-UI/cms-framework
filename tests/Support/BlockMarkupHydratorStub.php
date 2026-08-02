<?php

/**
 * Test-only stand-in for visual-editor's block-markup hydrator.
 *
 * {@see \ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\ThemeFileBlockParser}
 * resolves `BlockMarkupHydrator` out of the container by name so cms-framework
 * stays installable without visual-editor. Tests bind an instance of this class
 * under that name to exercise the hydrated branch — no dev dependency on
 * visual-editor, and no autoloaded class shadowing the real one (which would
 * make `class_exists()` true for every later test in the process).
 *
 * @since      2.7.2
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Support;

use RuntimeException;

/**
 * @since 2.7.2
 */
class BlockMarkupHydratorStub
{
    /**
     * Markup strings passed to {@see hydrate()}, in call order. Lets a test
     * assert the resolver handed over the file contents verbatim.
     *
     * @since 2.7.2
     *
     * @var array<int, string>
     */
    public array $received = [];

    /**
     * @since 2.7.2
     *
     * @param  bool  $shouldThrow  When true, {@see hydrate()} throws so tests can cover the fallback path.
     */
    public function __construct(
        public bool $shouldThrow = false,
    ) {
    }

    /**
     * Return a marker tree in the editor shape so assertions can tell a
     * hydrated result apart from the WP `parse_blocks()` fallback.
     *
     * @since 2.7.2
     *
     * @param  string  $markup  The raw block markup.
     *
     * @return array<int, array<string, mixed>> The stub's editor-shape tree.
     */
    public function hydrate( string $markup ): array
    {
        $this->received[] = $markup;

        if ( $this->shouldThrow ) {
            throw new RuntimeException( 'Stub hydration failure.' );
        }

        return [
            [
                'name'        => 'core/paragraph',
                'attributes'  => ['content' => 'hydrated'],
                'innerBlocks' => [],
            ],
        ];
    }
}
