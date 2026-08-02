<?php

/**
 * Template Resolver
 *
 * Resolves template entities for the active theme by merging DB-stored
 * templates with theme-file templates from `themes/{active}/templates/{slug}.html`.
 * DB rows win when present.
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
        $themeFile    = $this->themeFilePath( $theme, $slug );
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
                area         : null,
                model        : $row,
            );
        }

        if ( null === $themeFile ) {
            return null;
        }

        $markup = File::get( $themeFile );

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
     * Returns the absolute path to the theme file for the given slug,
     * or null when the file does not exist.
     *
     * @since 2.0.0
     */
    protected function themeFilePath( string $theme, string $slug ): ?string
    {
        $directory = config( 'cms.themes.directory', 'themes' );
        $path      = base_path( $directory ) . '/' . $theme . '/templates/' . $slug . '.html';

        return File::exists( $path ) ? $path : null;
    }

    /**
     * Returns the list of template slugs the active theme provides on disk.
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
            if ( 'html' === $file->getExtension() ) {
                $slugs[] = $file->getFilenameWithoutExtension();
            }
        }

        return $slugs;
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
