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

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\BlockPattern;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedEntity;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedPattern;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared block content filtering for the Site Editor write controllers.
 *
 * The host controller exposes its entity resolver as `$this->resolver`
 * (a `TemplateResolver`, `TemplatePartResolver` or `PatternResolver`), so a
 * create that overrides a theme file can pass the file's blocks along.
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
     * @param  array<int, mixed>|null  $themeFileBlocks  On create, the blocks of the theme file the new row overrides, or null when there is none.
     *
     * @return array<string, mixed> The attributes with the filtered block content.
     */
    protected function filterBlockContent( array $attributes, ?Model $existing, string $context, ?array $themeFileBlocks = null ): array
    {
        if ( ! $this->hasBlockContent( $attributes ) ) {
            return $attributes;
        }

        $storedBlocks = $themeFileBlocks;

        if ( null !== $existing ) {
            $stored       = $existing->getAttribute( 'block_content' );
            $storedBlocks = is_array( $stored ) ? $stored : [];
        }

        $blocks   = $attributes['block_content'];
        $filtered = applyFilters( 'ap.cmsFramework.siteEditor.blockContent', $blocks, $storedBlocks, $context );

        $attributes['block_content'] = is_array( $filtered ) ? $filtered : $blocks;

        return $attributes;
    }

    /**
     * Filter the block content of a row about to be created.
     *
     * When the row is the first DB override of a theme file (a template,
     * template part or theme pattern with the same slug), the file's parsed
     * blocks, the version the Site Editor showed before the save, are passed
     * as the stored blocks, so a guard keeps the Custom CSS the theme ships.
     * Otherwise the stored blocks are null.
     *
     * @since 2.15.0
     *
     * @param  array<string, mixed>  $attributes  The attributes about to be created, including `slug`.
     * @param  string  $context  The entity kind: `template`, `template-part`, `block` or `block-pattern`.
     *
     * @return array<string, mixed> The attributes with the filtered block content.
     */
    protected function filterBlockContentForCreate( array $attributes, string $context ): array
    {
        if ( ! $this->hasBlockContent( $attributes ) ) {
            return $attributes;
        }

        $slug = isset( $attributes['slug'] ) && is_string( $attributes['slug'] ) ? $attributes['slug'] : null;

        return $this->filterBlockContent( $attributes, null, $context, $this->themeFileBlocks( $slug ) );
    }

    /**
     * The parsed blocks of the active theme's file for a slug.
     *
     * Uses the controller's resolver, the same source the index and show
     * endpoints read. Returns null when no theme file backs the slug, when a
     * DB row already resolves for it, or when the file is a read-only Blade
     * template.
     *
     * @since 2.15.0
     *
     * @param  string|null  $slug  The user-facing slug.
     *
     * @return array<int, mixed>|null The theme file's block tree, or null.
     */
    protected function themeFileBlocks( ?string $slug ): ?array
    {
        if ( null === $slug ) {
            return null;
        }

        $resolved = $this->resolver->resolve( $slug );

        if ( $resolved instanceof ResolvedEntity ) {
            return 'theme' === $resolved->source && ! $resolved->isBlade ? $resolved->blocks : null;
        }

        if ( $resolved instanceof ResolvedPattern ) {
            return BlockPattern::SOURCE_THEME === $resolved->source ? $resolved->blocks : null;
        }

        return null;
    }

    /**
     * Whether the attributes carry a `block_content` array to filter.
     *
     * @since 2.15.0
     *
     * @param  array<string, mixed>  $attributes  The attributes about to be written.
     */
    protected function hasBlockContent( array $attributes ): bool
    {
        return array_key_exists( 'block_content', $attributes ) && is_array( $attributes['block_content'] );
    }
}
