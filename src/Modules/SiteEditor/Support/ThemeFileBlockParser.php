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

use Composer\InstalledVersions;
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

        return self::parseWith( self::hydrator(), $markup )['blocks'];
    }

    /**
     * Parse raw theme-file block markup, reusing an earlier parse of the
     * same markup when one is cached.
     *
     * Same result as {@see parse()}, memoised through
     * {@see ParsedThemeFileCache} so listing a theme's templates and parts
     * does not re-hydrate every file on every request (#424). The cache key
     * includes the hydrator in play, so a tree parsed without visual-editor
     * is never served once it is installed (or the reverse), and a parse
     * whose hydration threw is kept out of the cache store so the next
     * request retries it.
     *
     * @since 2.15.6
     *
     * @param  string  $markup  The raw contents of a theme `.html` file.
     *
     * @return array<int, array<string, mixed>> The parsed block tree.
     */
    public static function parseCached( string $markup ): array
    {
        if ( '' === trim( $markup ) ) {
            return [];
        }

        $hydrator = self::hydrator();

        return app( ParsedThemeFileCache::class )->remember(
            'blocks',
            $markup,
            self::hydratorSignature( $hydrator ),
            function ( bool &$store ) use ( $hydrator, $markup ): array {
                $result = self::parseWith( $hydrator, $markup );
                $store  = ! $result['degraded'];

                return $result['blocks'];
            },
        );
    }

    /**
     * Parse non-blank markup with the given hydrator, falling back to the
     * WP `parse_blocks()` shape when there is none or it throws.
     *
     * @since 2.15.6
     *
     * @param  object|null  $hydrator  visual-editor's hydrator, or null when unavailable.
     * @param  string       $markup    The raw block markup.
     *
     * @return array{blocks: array<int, array<string, mixed>>, degraded: bool} The tree, and whether the hydrator threw.
     */
    private static function parseWith( ?object $hydrator, string $markup ): array
    {
        if ( null !== $hydrator ) {
            try {
                return ['blocks' => $hydrator->hydrate( $markup ), 'degraded' => false];
            } catch ( Throwable $exception ) {
                // A hydrator failure must not take the whole template down —
                // the WP-shape parse below still gives consumers a tree.
                Log::warning(
                    'ThemeFileBlockParser: block hydration failed; falling back to the parse_blocks() shape.',
                    [
                        'exception' => $exception->getMessage(),
                    ],
                );

                return ['blocks' => BlockMarkupParser::parse( $markup ), 'degraded' => true];
            }
        }

        return ['blocks' => BlockMarkupParser::parse( $markup ), 'degraded' => false];
    }

    /**
     * Identify the hydrator for cache keys: its class plus the installed
     * visual-editor build, so an upgrade that changes the hydrated shape
     * misses the cache instead of serving trees from the old version.
     *
     * @since 2.15.6
     */
    private static function hydratorSignature( ?object $hydrator ): string
    {
        if ( null === $hydrator ) {
            return 'parse_blocks';
        }

        $build = '';

        if ( class_exists( InstalledVersions::class ) && InstalledVersions::isInstalled( 'artisanpack-ui/visual-editor' ) ) {
            $build = ( InstalledVersions::getPrettyVersion( 'artisanpack-ui/visual-editor' ) ?? '' ) . '@' . ( InstalledVersions::getReference( 'artisanpack-ui/visual-editor' ) ?? '' );
        }

        return $hydrator::class . '|' . $build;
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
