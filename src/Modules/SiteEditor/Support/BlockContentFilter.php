<?php

/**
 * Block Content Filter
 *
 * Single entry point for the `ap.cmsFramework.siteEditor.blockContent`
 * filter, which every Site Editor write that saves a block tree runs before
 * the row is written: the API controllers and theme-file copies such as
 * {@see \ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\PatternResolver::cloneToUser()}.
 *
 * @since      2.15.1
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support;

/**
 * @since 2.15.1
 */
final class BlockContentFilter
{
    /**
     * The filter hook name.
     *
     * @since 2.15.1
     */
    public const HOOK = 'ap.cmsFramework.siteEditor.blockContent';

    /**
     * Run a block tree through the block content filter.
     *
     * A callback that returns something other than an array is ignored and
     * the incoming blocks are returned unchanged.
     *
     * @since 2.15.1
     *
     * @param  array<int, mixed>  $blocks  The block tree about to be saved.
     * @param  array<int, mixed>|null  $storedBlocks  The record's stored blocks, the theme file's blocks when the save copies or first overrides a theme file, or null.
     * @param  string  $context  The entity kind: `template`, `template-part`, `block` or `block-pattern`.
     *
     * @return array<int, mixed> The filtered block tree.
     */
    public static function apply( array $blocks, ?array $storedBlocks, string $context ): array
    {
        $filtered = applyFilters( self::HOOK, $blocks, $storedBlocks, $context );

        return is_array( $filtered ) ? $filtered : $blocks;
    }
}
