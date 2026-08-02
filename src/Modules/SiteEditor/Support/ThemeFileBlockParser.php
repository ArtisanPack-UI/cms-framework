<?php

/**
 * Theme File Block Parser
 *
 * Turns a block theme's raw `.html` markup (`templates/{slug}.html`,
 * `parts/{slug}.html`) into the block tree `ResolvedEntity::$blocks` carries,
 * so consumers get an authoritative tree regardless of whether the entity
 * came from the DB or from disk (#274). See
 * {@see \ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedEntity}
 * for the contract that tree satisfies.
 *
 * ## Why this isn't just `BlockMarkupParser::parse()`
 *
 * {@see BlockMarkupParser} emits the WP `parse_blocks()` shape
 * (`{blockName, attrs, innerHTML}`), but a DB row's `block_content` column
 * holds the editor shape (`{name, attributes, innerBlocks}`) the visual
 * editor serializes. Handing consumers the WP shape for theme files and the
 * editor shape for DB rows would push a shape sniff onto every one of them.
 *
 * Worse, a key rename alone would not close the gap: Gutenberg persists most
 * block text in the saved HTML rather than in the delimiter's JSON, so a
 * renamed tree renders structurally correct but textless. Recovering those
 * attributes needs each block type's `block.json` `source` definitions, which
 * live with the block partials in visual-editor — hence
 * `BlockMarkupHydrator` (visual-editor#688) rather than a local converter.
 *
 * ## Degradation
 *
 * visual-editor is not a cms-framework dependency, so the hydrator is looked
 * up by name. Without it — cms-framework running standalone, or with a
 * visual-editor older than 1.5.5 — this falls back to the WP-shape parse.
 * That is still strictly better than the empty array shipped before #274
 * (consumers that sniff both key sets render something), but text recovery
 * only happens with the hydrator present.
 *
 * @since      2.7.2
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @since 2.7.2
 */
final class ThemeFileBlockParser
{
    /**
     * visual-editor's markup hydrator. Referenced as a string, and without
     * a leading backslash so the name matches the key Laravel's container
     * stores `BlockMarkupHydrator::class` under — a leading slash makes
     * `app()` treat it as a distinct binding and build a fresh instance,
     * ignoring visual-editor's singleton and any test override.
     *
     * @since 2.7.2
     */
    public const HYDRATOR_CLASS = 'ArtisanPackUI\\VisualEditor\\Support\\BlockMarkupHydrator';

    /**
     * Parse raw theme-file block markup into a block tree.
     *
     * Returns the editor shape when visual-editor's hydrator is available,
     * and the WP `parse_blocks()` shape otherwise. Blank markup yields an
     * empty array from either path.
     *
     * @since 2.7.2
     *
     * @param  string  $markup  The raw contents of a theme `.html` file.
     *
     * @return array<int, array<string, mixed>> The parsed block tree.
     */
    public static function parse( string $markup ): array
    {
        if ( '' === trim( $markup ) ) {
            return [];
        }

        $hydrator = self::hydrator();

        if ( null !== $hydrator ) {
            try {
                return $hydrator->hydrate( $markup );
            } catch ( Throwable $exception ) {
                // A hydrator failure must not take the whole template down —
                // the WP-shape parse below still gives consumers a tree.
                Log::warning(
                    'ThemeFileBlockParser: block hydration failed; falling back to the parse_blocks() shape.',
                    [
                        'exception' => $exception->getMessage(),
                    ],
                );
            }
        }

        return BlockMarkupParser::parse( $markup );
    }

    /**
     * Resolve visual-editor's hydrator, or null when it is unavailable.
     *
     * Checks the container binding first so a host (or a test) can supply
     * its own hydrator without the concrete class being on the classpath;
     * falls back to `class_exists` for the ordinary installed case, where
     * the container auto-resolves the concrete class.
     *
     * @since 2.7.2
     */
    private static function hydrator(): ?object
    {
        $container = app();

        if ( ! $container->bound( self::HYDRATOR_CLASS ) && ! class_exists( self::HYDRATOR_CLASS ) ) {
            return null;
        }

        try {
            $hydrator = $container->make( self::HYDRATOR_CLASS );
        } catch ( Throwable $exception ) {
            Log::warning(
                'ThemeFileBlockParser: could not resolve the block hydrator; falling back to the parse_blocks() shape.',
                [
                    'exception' => $exception->getMessage(),
                ],
            );

            return null;
        }

        return is_object( $hydrator ) && method_exists( $hydrator, 'hydrate' ) ? $hydrator : null;
    }
}
