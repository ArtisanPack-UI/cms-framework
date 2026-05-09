<?php

/**
 * Template Part Resolver
 *
 * Resolves template-part entities for the active theme by merging DB-stored
 * parts with theme-file parts from `themes/{active}/parts/{slug}.html`.
 * DB rows win when present.
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\TemplatePart;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SlugValidator;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Support\Facades\File;

/**
 * @since 1.2.0
 */
class TemplatePartResolver implements EntityResolver
{
    /**
     * @since 1.2.0
     */
    public function __construct(
        private ThemeManager $themeManager,
    ) {}

    /**
     * @since 1.2.0
     */
    public function resolve(string $slug): ?ResolvedEntity
    {
        if (! $this->isValidSlug($slug)) {
            return null;
        }

        $theme = $this->activeThemeSlug();

        if (null === $theme) {
            return null;
        }

        $row          = TemplatePart::query()->where('theme', $theme)->where('slug', $slug)->first();
        $themeFile    = $this->themeFilePath($theme, $slug);
        $hasThemeFile = null !== $themeFile;

        if (null !== $row) {
            return new ResolvedEntity(
                slug         : $row->slug,
                theme        : $row->theme,
                source       : 'db',
                raw          : '',
                blocks       : is_array($row->block_content) ? $row->block_content : [],
                title        : $row->title,
                description  : $row->description,
                status       : $row->status,
                hasThemeFile : $hasThemeFile,
                isCustom     : (bool) $row->is_custom && ! $hasThemeFile,
                area         : $row->area,
                model        : $row,
            );
        }

        if (null === $themeFile) {
            return null;
        }

        return new ResolvedEntity(
            slug         : $slug,
            theme        : $theme,
            source       : 'theme',
            raw          : File::get($themeFile),
            blocks       : [],
            title        : $this->humanizeSlug($slug),
            description  : null,
            status       : 'publish',
            hasThemeFile : true,
            isCustom     : false,
            area         : $this->guessAreaFromSlug($slug),
            model        : null,
        );
    }

    /**
     * @since 1.2.0
     *
     * @return array<int, ResolvedEntity>
     */
    public function all(): array
    {
        $theme = $this->activeThemeSlug();

        if (null === $theme) {
            return [];
        }

        $themeFileSlugs = $this->themeFileSlugs($theme);
        $rows           = TemplatePart::query()->where('theme', $theme)->get()->keyBy('slug');

        $allSlugs = array_unique(array_merge($themeFileSlugs, $rows->keys()->all()));
        sort($allSlugs);

        $resolved = [];

        foreach ($allSlugs as $slug) {
            $entity = $this->resolve($slug);

            if (null !== $entity) {
                $resolved[] = $entity;
            }
        }

        return $resolved;
    }

    /**
     * @since 1.2.0
     */
    public function revert(string $slug): bool
    {
        if (! $this->isValidSlug($slug)) {
            return false;
        }

        $theme = $this->activeThemeSlug();

        if (null === $theme) {
            return false;
        }

        return TemplatePart::query()->where('theme', $theme)->where('slug', $slug)->delete() > 0;
    }

    /**
     * Defense-in-depth slug guard, delegating to {@see SlugValidator}.
     *
     * @since 1.2.0
     */
    protected function isValidSlug(string $slug): bool
    {
        return SlugValidator::isValid($slug);
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
        $directory = config('cms.themes.directory', 'themes');
        $path      = base_path($directory).'/'.$theme.'/parts/'.$slug.'.html';

        return File::exists($path) ? $path : null;
    }

    /**
     * @since 1.2.0
     *
     * @return array<int, string>
     */
    protected function themeFileSlugs(string $theme): array
    {
        $directory = config('cms.themes.directory', 'themes');
        $dir       = base_path($directory).'/'.$theme.'/parts';

        if (! File::isDirectory($dir)) {
            return [];
        }

        $slugs = [];

        foreach (File::files($dir) as $file) {
            if ('html' === $file->getExtension()) {
                $slugs[] = $file->getFilenameWithoutExtension();
            }
        }

        return $slugs;
    }

    /**
     * @since 1.2.0
     */
    protected function humanizeSlug(string $slug): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    /**
     * Guess a template-part area from the slug for theme-file parts that
     * carry no metadata: e.g. `header`, `header-large` → `'header'`,
     * `footer` → `'footer'`, anything else → `'general'`.
     *
     * @since 1.2.0
     */
    protected function guessAreaFromSlug(string $slug): string
    {
        foreach (['header', 'footer', 'sidebar'] as $area) {
            if ($slug === $area || str_starts_with($slug, $area.'-')) {
                return $area;
            }
        }

        return 'general';
    }
}
