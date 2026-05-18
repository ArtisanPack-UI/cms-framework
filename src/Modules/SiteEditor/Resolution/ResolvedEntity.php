<?php

/**
 * Resolved Entity DTO
 *
 * Carries the result of an {@see EntityResolver::resolve()} call: the merged
 * source-of-truth content for a slug, plus enough metadata to reconstruct
 * a WP-shape REST response.
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\TemplatePart;

/**
 * @since 1.2.0
 */
final class ResolvedEntity
{
    /**
     * @since 1.2.0
     *
     * @param  string  $slug  The entity slug.
     * @param  string  $theme  The active theme slug at resolution time.
     * @param  'db'|'theme'  $source  Whether the resolved content came from a DB row or a theme file.
     * @param  string  $raw  The raw block markup string. Populated for theme files (the file contents) and empty for DB rows — cms-framework stores only the parsed block array, never a raw HTML mirror, to match the visual-editor adapter convention (`Adapters\CmsFramework\WpEntityResource`).
     * @param  array<int, array<string, mixed>>  $blocks  The parsed block tree. Populated for DB rows; empty for theme files (we don't parse `.html` on the fly in V1).
     * @param  string|null  $title  Display title; null when only a theme file exists and the file has no title metadata.
     * @param  string|null  $description  Display description.
     * @param  string  $status  WP status (`'publish'` by default).
     * @param  bool  $hasThemeFile  True when a theme file backs this slug (regardless of whether a DB override exists).
     * @param  bool  $isCustom  True when the entity was authored in the admin with no theme-file backing.
     * @param  string|null  $area  Template-part area (`'header' | 'footer' | 'sidebar' | 'uncategorized' | 'navigation-overlay'`); null for templates.
     * @param  Template|TemplatePart|null  $model  The DB row when `$source === 'db'`; null otherwise.
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $theme,
        public readonly string $source,
        public readonly string $raw,
        public readonly array $blocks,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly string $status,
        public readonly bool $hasThemeFile,
        public readonly bool $isCustom,
        public readonly ?string $area,
        public readonly Template|TemplatePart|null $model,
    ) {}

    /**
     * The integer ID of the backing DB row, or 0 when none exists.
     *
     * Maps to WP's `wp_id` field on template / template-part responses.
     *
     * @since 1.2.0
     */
    public function wpId(): int
    {
        return null !== $this->model ? (int) $this->model->id : 0;
    }

    /**
     * Convert to the array shape consumed by visual-editor's
     * {@see \ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplate::fromArray()}
     * (and {@see \ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplatePart::fromArray()}
     * when `area` is populated). Distinct from the WP REST `TemplateResource`
     * shape: REST emits `id`, `content.{raw,blocks}`, and `title.{raw,rendered}`,
     * while the filter expects `slug`, `theme`, top-level `raw_content`/`blocks`,
     * and a plain string `title`.
     *
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public function toFilterEntry(): array
    {
        $entry = [
            'slug'           => $this->slug,
            'theme'          => $this->theme,
            'title'          => $this->title ?? '',
            'description'    => $this->description ?? '',
            'status'         => $this->status,
            'source'         => $this->source,
            'raw_content'    => $this->raw,
            'blocks'         => $this->blocks,
            'has_theme_file' => $this->hasThemeFile,
            'is_custom'      => $this->isCustom,
            'wp_id'          => $this->wpId(),
            'author_id'      => null !== $this->model && null !== $this->model->author_id
                ? (int) $this->model->author_id
                : null,
            'modified_at'    => null !== $this->model
                ? optional($this->model->updated_at)->toIso8601String()
                : null,
        ];

        if (null !== $this->area) {
            $entry['area'] = $this->area;
        }

        return $entry;
    }
}
