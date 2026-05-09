<?php

/**
 * Pattern Resolver
 *
 * Surfaces both theme-shipped patterns (filesystem) and user-authored patterns
 * (DB rows) through a single read API. Unlike H1's templates, patterns do not
 * merge by slug: theme patterns are file-only and read-only, while user
 * patterns live exclusively in the DB. The two sources occupy disjoint
 * keyspaces — user patterns carry a `user/` prefix on their stored slug per
 * plan 14 §5.6 — and surface together in the merged map keyed by the storage
 * form so visual-editor's `ap.visual-editor.patterns` consumer can route to
 * the right code path per source.
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\BlockPattern;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\PatternFileParser;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SlugValidator;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * @since 1.2.0
 */
class PatternResolver
{
    /**
     * @since 1.2.0
     */
    public function __construct(
        private ThemeManager $themeManager,
    ) {}

    /**
     * Resolve a single pattern by either user-facing slug or storage-form
     * slug. Looks up user patterns first (DB), then falls back to theme files.
     *
     * @since 1.2.0
     */
    public function resolve(string $slug): ?ResolvedPattern
    {
        $userFacing = BlockPattern::stripUserPrefix($slug);

        if (! SlugValidator::isValid($userFacing)) {
            return null;
        }

        $row = BlockPattern::query()
            ->where('slug', BlockPattern::withUserPrefix($userFacing))
            ->where('source', BlockPattern::SOURCE_USER)
            ->first();

        if (null !== $row) {
            return $this->fromUserRow($row);
        }

        return $this->resolveThemePattern($userFacing);
    }

    /**
     * Return all patterns for the active theme, keyed by storage-form slug.
     *
     * Theme patterns are keyed by their natural slug (`my-pattern`); user
     * patterns by their `user/`-prefixed slug. This matches the expected key
     * shape on the visual-editor consumer side ({@see \ArtisanPackUI\VisualEditor\SiteEditor\Resolution\PatternResolver}).
     *
     * @since 1.2.0
     *
     * @return array<string, ResolvedPattern>
     */
    public function all(): array
    {
        $resolved = [];

        foreach ($this->resolveThemePatterns() as $themePattern) {
            $resolved[$themePattern->slug] = $themePattern;
        }

        foreach (BlockPattern::query()->where('source', BlockPattern::SOURCE_USER)->get() as $row) {
            $entity                     = $this->fromUserRow($row);
            $resolved[$entity->slug]    = $entity;
        }

        ksort($resolved);

        return $resolved;
    }

    /**
     * Convert all resolved patterns to the array shape expected by visual-editor's
     * `ap.visual-editor.patterns` filter.
     *
     * @since 1.2.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function toFilterMap(): array
    {
        $map = [];

        foreach ($this->all() as $key => $pattern) {
            $map[$key] = $pattern->toFilterEntry();
        }

        return $map;
    }

    /**
     * Clone a theme pattern into a new user-source DB row. The new row uses
     * the same user-facing slug and inherits title / description / categories /
     * block types from the theme file.
     *
     * @since 1.2.0
     *
     * @throws RuntimeException When no theme pattern exists for the slug.
     */
    public function cloneToUser(string $themeSlug): BlockPattern
    {
        $themePattern = $this->resolveThemePattern($themeSlug);

        if (null === $themePattern) {
            throw new RuntimeException("No theme pattern found for slug '{$themeSlug}'.");
        }

        return BlockPattern::create([
            'slug'        => $themePattern->userFacingSlug,
            'theme'       => null,
            'title'       => $themePattern->title,
            'description' => $themePattern->description,
            'source'      => BlockPattern::SOURCE_USER,
            'synced'      => false,
            'categories'  => $themePattern->categories,
            'block_types' => $themePattern->blockTypes,
        ]);
    }

    /**
     * Build a {@see ResolvedPattern} from a user-source DB row.
     *
     * @since 1.2.0
     */
    protected function fromUserRow(BlockPattern $row): ResolvedPattern
    {
        $blocks = is_array($row->block_content) ? $row->block_content : [];

        return new ResolvedPattern(
            slug           : $row->slug,
            userFacingSlug : $row->userFacingSlug(),
            theme          : null,
            title          : $row->title,
            description    : $row->description,
            source         : BlockPattern::SOURCE_USER,
            synced         : (bool) $row->synced,
            rawContent     : '',
            blocks         : $blocks,
            categories     : is_array($row->categories) ? array_values(array_filter($row->categories, 'is_string')) : [],
            blockTypes     : is_array($row->block_types) ? array_values(array_filter($row->block_types, 'is_string')) : [],
            model          : $row,
        );
    }

    /**
     * Read a single theme pattern by user-facing slug. Returns null when no
     * matching file exists in the active theme.
     *
     * @since 1.2.0
     */
    protected function resolveThemePattern(string $slug): ?ResolvedPattern
    {
        $theme = $this->activeThemeSlug();

        if (null === $theme) {
            return null;
        }

        $file = $this->themeFilePath($theme, $slug);

        if (null === $file) {
            return null;
        }

        return $this->buildThemePattern($theme, $slug, File::get($file));
    }

    /**
     * Walk the active theme's `patterns/` directory and build entities for each
     * `.php` file found.
     *
     * @since 1.2.0
     *
     * @return array<int, ResolvedPattern>
     */
    protected function resolveThemePatterns(): array
    {
        $theme = $this->activeThemeSlug();

        if (null === $theme) {
            return [];
        }

        $directory = $this->themePatternsDirectory($theme);

        if (! File::isDirectory($directory)) {
            return [];
        }

        $patterns = [];

        foreach (File::files($directory) as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $slug = $file->getFilenameWithoutExtension();

            if (! SlugValidator::isValid($slug)) {
                continue;
            }

            $patterns[] = $this->buildThemePattern($theme, $slug, File::get($file->getRealPath()));
        }

        return $patterns;
    }

    /**
     * Build a theme {@see ResolvedPattern} from a parsed file's contents.
     *
     * @since 1.2.0
     */
    protected function buildThemePattern(string $theme, string $slug, string $contents): ResolvedPattern
    {
        $parsed = PatternFileParser::parse($contents);

        return new ResolvedPattern(
            slug           : $slug,
            userFacingSlug : $slug,
            theme          : $theme,
            title          : '' !== $parsed['title'] ? $parsed['title'] : $this->humanizeSlug($slug),
            description    : $parsed['description'],
            source         : BlockPattern::SOURCE_THEME,
            synced         : false,
            rawContent     : $parsed['content'],
            blocks         : [],
            categories     : $parsed['categories'],
            blockTypes     : $parsed['block_types'],
            model          : null,
        );
    }

    /**
     * @since 1.2.0
     */
    protected function activeThemeSlug(): ?string
    {
        $theme = $this->themeManager->getActiveTheme();

        return null !== $theme && ! empty($theme['slug']) ? (string) $theme['slug'] : null;
    }

    /**
     * @since 1.2.0
     */
    protected function themeFilePath(string $theme, string $slug): ?string
    {
        $path = $this->themePatternsDirectory($theme).'/'.$slug.'.php';

        return File::exists($path) ? $path : null;
    }

    /**
     * @since 1.2.0
     */
    protected function themePatternsDirectory(string $theme): string
    {
        $directory = (string) config('cms.themes.directory', 'themes');

        return base_path($directory).'/'.$theme.'/patterns';
    }

    /**
     * Convert a slug like `hero-banner` to a human title `Hero Banner`. Used
     * as the default title when a theme pattern omits the `Title:` header.
     *
     * @since 1.2.0
     */
    protected function humanizeSlug(string $slug): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }
}
