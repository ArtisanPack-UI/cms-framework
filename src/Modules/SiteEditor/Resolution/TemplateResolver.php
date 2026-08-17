<?php

/**
 * Template Resolver
 *
 * Resolves template entities for the active theme by merging DB-stored
 * templates with theme-file templates from `themes/{active}/templates/{slug}.html`,
 * falling back to a Blade file at `themes/{active}/templates/{slug}.blade.php`
 * when no HTML file exists. DB rows win over theme files; HTML wins over Blade.
 * Blade files are read-only in the site editor.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SlugValidator;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\ThemeFileBlockParser;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Support\Facades\File;

/**
 * @since 2.0.0
 */
class TemplateResolver implements EntityResolver
{
    /**
     * @since 2.0.0
     */
    public function __construct(
        private ThemeManager $themeManager,
    ) {
    }

    /**
     * @since 2.0.0
     */
    public function resolve( string $slug ): ?ResolvedEntity
    {
        if ( ! $this->isValidSlug( $slug ) ) {
            return null;
        }

        $theme = $this->activeThemeSlug();

        if ( null === $theme ) {
            return null;
        }

        $row          = Template::query()->where( 'theme', $theme )->where( 'slug', $slug )->first();
        $themeFile    = $this->resolveThemeFile( $theme, $slug );
        $hasThemeFile = null !== $themeFile;

        if ( null !== $row ) {
            return new ResolvedEntity(
                slug         : $row->slug,
                theme        : $row->theme,
                source       : 'db',
                raw          : '',
                blocks       : is_array( $row->block_content ) ? $row->block_content : [],
                title        : $row->title,
                description  : $row->description,
                status       : $row->status,
                hasThemeFile : $hasThemeFile,
                isCustom     : (bool) $row->is_custom && ! $hasThemeFile,
                isBlade      : false,
                area         : null,
                model        : $row,
            );
        }

        if ( null === $themeFile ) {
            return null;
        }

        // Blade theme files render at request time and are read-only in the
        // site editor. They carry no block tree, so `raw` and `blocks` stay
        // empty and the `isBlade` flag drives the editor's read-only affordance
        // and the save-rejection guard in TemplatesController.
        if ( $themeFile['isBlade'] ) {
            return new ResolvedEntity(
                slug         : $slug,
                theme        : $theme,
                source       : 'theme',
                raw          : '',
                blocks       : [],
                title        : $this->humanizeSlug( $slug ),
                description  : null,
                status       : 'publish',
                hasThemeFile : true,
                isCustom     : false,
                isBlade      : true,
                area         : null,
                model        : null,
            );
        }

        $markup = File::get( $themeFile['path'] );

        return new ResolvedEntity(
            slug         : $slug,
            theme        : $theme,
            source       : 'theme',
            raw          : $markup,
            blocks       : ThemeFileBlockParser::parse( $markup ),
            title        : $this->humanizeSlug( $slug ),
            description  : null,
            status       : 'publish',
            hasThemeFile : true,
            isCustom     : false,
            isBlade      : false,
            area         : null,
            model        : null,
        );
    }

    /**
     * @since 2.0.0
     *
     * @return array<int, ResolvedEntity>
     */
    public function all(): array
    {
        $theme = $this->activeThemeSlug();

        if ( null === $theme ) {
            return [];
        }

        $themeFileSlugs = $this->themeFileSlugs( $theme );
        $rows           = Template::query()->where( 'theme', $theme )->get()->keyBy( 'slug' );

        $allSlugs = array_unique( array_merge( $themeFileSlugs, $rows->keys()->all() ) );
        sort( $allSlugs );

        $resolved = [];

        foreach ( $allSlugs as $slug ) {
            $entity = $this->resolve( $slug );

            if ( null !== $entity ) {
                $resolved[] = $entity;
            }
        }

        return $resolved;
    }

    /**
     * @since 2.0.0
     */
    public function revert( string $slug ): bool
    {
        if ( ! $this->isValidSlug( $slug ) ) {
            return false;
        }

        $theme = $this->activeThemeSlug();

        if ( null === $theme ) {
            return false;
        }

        return Template::query()->where( 'theme', $theme )->where( 'slug', $slug )->delete() > 0;
    }

    /**
     * Defense-in-depth slug guard, delegating to {@see SlugValidator} so the
     * pattern lives in one place across resolvers, controllers, and Form
     * Requests.
     *
     * @since 2.0.0
     */
    protected function isValidSlug( string $slug ): bool
    {
        return SlugValidator::isValid( $slug );
    }

    /**
     * Returns the slug of the active theme, or null when no theme is active.
     *
     * @since 2.0.0
     */
    protected function activeThemeSlug(): ?string
    {
        $theme = $this->themeManager->getActiveTheme();

        return null !== $theme && ! empty( $theme['slug'] ) ? (string) $theme['slug'] : null;
    }

    /**
     * Resolve the theme file backing a slug, preferring block-grammar HTML
     * over a Blade fallback.
     *
     * A slug may be authored either as `templates/{slug}.html` (block grammar,
     * editable in the site editor) or `templates/{slug}.blade.php` (rendered at
     * request time, read-only in the site editor). When both exist HTML wins,
     * so a theme can migrate a file from Blade to blocks without changing its
     * slug. Returns the absolute path and whether it is a Blade file, or null
     * when neither exists.
     *
     * @since 2.9.0
     *
     * @return array{path: string, isBlade: bool}|null
     */
    protected function resolveThemeFile( string $theme, string $slug ): ?array
    {
        $directory = config( 'cms.themes.directory', 'themes' );
        $base      = base_path( $directory ) . '/' . $theme . '/templates/' . $slug;

        if ( File::exists( $base . '.html' ) ) {
            return ['path' => $base . '.html', 'isBlade' => false];
        }

        if ( File::exists( $base . '.blade.php' ) ) {
            return ['path' => $base . '.blade.php', 'isBlade' => true];
        }

        return null;
    }

    /**
     * Returns the list of template slugs the active theme provides on disk,
     * including both block-grammar HTML files and Blade fallbacks.
     *
     * @since 2.0.0
     *
     * @return array<int, string>
     */
    protected function themeFileSlugs( string $theme ): array
    {
        $directory = config( 'cms.themes.directory', 'themes' );
        $dir       = base_path( $directory ) . '/' . $theme . '/templates';

        if ( ! File::isDirectory( $dir ) ) {
            return [];
        }

        $slugs = [];

        foreach ( File::files( $dir ) as $file ) {
            $name = $file->getFilename();

            // `getFilenameWithoutExtension()` strips only the final extension,
            // so `page.blade.php` would yield `page.blade`; match the compound
            // `.blade.php` suffix explicitly before falling back to `.html`.
            if ( str_ends_with( $name, '.blade.php' ) ) {
                $slugs[] = substr( $name, 0, -strlen( '.blade.php' ) );
            } elseif ( 'html' === $file->getExtension() ) {
                $slugs[] = $file->getFilenameWithoutExtension();
            }
        }

        return array_values( array_unique( $slugs ) );
    }

    /**
     * Convert a slug like `home-archive` to a human title `Home Archive`.
     *
     * Used as the default title when a theme-file template has no
     * accompanying metadata.
     *
     * @since 2.0.0
     */
    protected function humanizeSlug( string $slug ): string
    {
        return ucwords( str_replace( ['-', '_'], ' ', $slug ) );
    }
}
