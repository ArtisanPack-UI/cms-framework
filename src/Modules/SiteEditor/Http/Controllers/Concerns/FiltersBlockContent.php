<?php

/**
 * Filters Block Content concern.
 *
 * Runs incoming Site Editor block content through the
 * `ap.cmsFramework.siteEditor.blockContent` filter before it is written, so a
 * host (or visual-editor) can veto or rewrite parts of the block tree, such as
 * per-block Custom CSS a user isn't allowed to change.
 *
 * @since      2.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared block content filtering for the Site Editor write controllers.
 *
 * @since 2.15.0
 */
trait FiltersBlockContent
{
    /**
     * Run the `block_content` attribute through the block content filter.
     *
     * Leaves the attributes untouched when they carry no `block_content`
     * array (the write doesn't change the stored blocks). Otherwise the
     * blocks, the record's stored blocks and the context go through
     * `ap.cmsFramework.siteEditor.blockContent`. A callback that returns
     * something other than an array is ignored and the incoming blocks are
     * kept.
     *
     * @since 2.15.0
     *
     * @param  array<string, mixed>  $attributes  The attributes about to be written.
     * @param  Model|null  $existing  The record being updated, or null on create.
     * @param  string  $context  The entity kind: `template`, `template-part`, `block` or `block-pattern`.
     *
     * @return array<string, mixed> The attributes with the filtered block content.
     */
    protected function filterBlockContent( array $attributes, ?Model $existing, string $context ): array
    {
        if ( ! array_key_exists( 'block_content', $attributes ) || ! is_array( $attributes['block_content'] ) ) {
            return $attributes;
        }

        $storedBlocks = null;

        if ( null !== $existing ) {
            $stored       = $existing->getAttribute( 'block_content' );
            $storedBlocks = is_array( $stored ) ? $stored : [];
        }

        $blocks   = $attributes['block_content'];
        $filtered = applyFilters( 'ap.cmsFramework.siteEditor.blockContent', $blocks, $storedBlocks, $context );

        $attributes['block_content'] = is_array( $filtered ) ? $filtered : $blocks;

        return $attributes;
    }
}
