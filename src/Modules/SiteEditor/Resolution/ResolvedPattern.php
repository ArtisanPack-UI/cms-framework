<?php

/**
 * Resolved Pattern DTO
 *
 * Carries the result of a {@see PatternResolver::resolve()} call: the merged
 * source-of-truth for a single block pattern, plus enough metadata to
 * reconstruct both a WP-shape REST response and the array form consumed by
 * visual-editor's `ap.visual-editor.patterns` filter.
 *
 * Distinct from H1's {@see ResolvedEntity}: patterns have no theme/DB merge
 * by slug (theme patterns are file-only, user patterns are DB-only) and carry
 * pattern-specific metadata (sync flag, categories, block types) that wouldn't
 * fit cleanly on the templates DTO.
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\BlockPattern;

/**
 * @since 1.2.0
 */
final class ResolvedPattern
{
    /**
     * @since 1.2.0
     *
     * @param  string  $slug  Storage-form slug. Theme patterns use the raw slug
     *                        (`my-pattern`); user patterns carry the `user/`
     *                        prefix per plan 14 §5.6.
     * @param  string  $userFacingSlug  Slug as shown to clients (no `user/` prefix).
     * @param  string|null  $theme  Active theme slug; null for user patterns.
     * @param  string  $title  Display title.
     * @param  'theme'|'user'  $source
     * @param  bool  $synced  True for synced (live-edited) user patterns;
     *                        always false for theme patterns.
     * @param  string  $rawContent  Serialized block markup string.
     * @param  array<int, array<string, mixed>>  $blocks  Parsed block tree (DB rows).
     * @param  array<int, string>  $categories  Pattern category slugs.
     * @param  array<int, string>  $blockTypes  WP `blockTypes` hint for the inserter.
     * @param  BlockPattern|null  $model  The DB row when `source === 'user'`; null otherwise.
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $userFacingSlug,
        public readonly ?string $theme,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $source,
        public readonly bool $synced,
        public readonly string $rawContent,
        public readonly array $blocks,
        public readonly array $categories,
        public readonly array $blockTypes,
        public readonly ?BlockPattern $model,
    ) {}

    /**
     * The integer ID of the backing DB row, or 0 for theme patterns.
     *
     * Maps to WP's `wp_id` field on pattern responses.
     *
     * @since 1.2.0
     */
    public function wpId(): int
    {
        return null !== $this->model ? (int) $this->model->id : 0;
    }

    /**
     * Convert to the array shape consumed by visual-editor's
     * {@see \ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedPattern::fromArray()}.
     *
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public function toFilterEntry(): array
    {
        return [
            'slug'        => $this->slug,
            'title'       => $this->title,
            'raw_content' => $this->rawContent,
            'blocks'      => $this->blocks,
            'source'      => $this->source,
            'synced'      => $this->synced,
            'categories'  => $this->categories,
            'block_types' => $this->blockTypes,
            'wp_id'       => $this->wpId(),
        ];
    }
}
