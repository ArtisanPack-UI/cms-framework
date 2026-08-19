<?php

/**
 * Template Part Resolver
 *
 * Resolves template-part entities for the active theme by merging DB-stored
 * parts with theme-file parts from `themes/{active}/parts/{slug}.html`,
 * falling back to a Blade file at `themes/{active}/parts/{slug}.blade.php`
 * when no HTML file exists. DB rows win over theme files; HTML wins over Blade.
 * Blade files are read-only in the site editor.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\TemplatePart;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SlugValidator;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\ThemeFileBlockParser;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Illuminate\Support\Facades\File;

/**
 * @since 2.0.0
 */
class TemplatePartResolver implements EntityResolver
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

        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput.VariableNotSanitized -- $theme is the active theme slug (internal config); $slug is guarded by SlugValidator at the top of this method. Both are bound as Eloquent query parameters.
        $row          = TemplatePart::query()->where( 'theme', $theme )->where( 'slug', $slug )->first();
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
                area         : $row->area,
                model        : $row,
            );
        }

        if ( null === $themeFile ) {
            return null;
        }

        // Blade theme files render at request time and are read-only in the
        // site editor. They carry no block tree, so `raw` and `blocks` stay
        // empty and the `isBlade` flag drives the editor's read-only affordance
        // and the save-rejection guard in TemplatePartsController.
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
                area         : $this->guessAreaFromSlug( $slug ),
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
            area         : $this->guessAreaFromSlug( $slug ),
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
        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput.VariableNotSanitized -- $theme is the active theme slug from ThemeManager (internal config, not request input) and is bound as an Eloquent query parameter.
        $rows           = TemplatePart::query()->where( 'theme', $theme )->get()->keyBy( 'slug' );

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

        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput.VariableNotSanitized -- $theme is the active theme slug (internal config); $slug is guarded by SlugValidator at the top of this method. Both are bound as Eloquent query parameters.
        return TemplatePart::query()->where( 'theme', $theme )->where( 'slug', $slug )->delete() > 0;
    }

    /**
     * Defense-in-depth slug guard, delegating to {@see SlugValidator}.
     *
     * @since 2.0.0
     */
    protected function isValidSlug( string $slug ): bool
    {
        return SlugValidator::isValid( $slug );
    }

    /**
     * @since 2.0.0
     */
    protected function activeThemeSlug(): ?string
    {
        $theme = $this->themeManager->getActiveTheme();

        return null !== $theme && ! empty( $theme['slug'] ) ? (string) $theme['slug'] : null;
    }

    /**
     * Resolve the theme file backing a part slug, preferring block-grammar HTML
     * over a Blade fallback. When both exist HTML wins. Returns the absolute
     * path and whether it is a Blade file, or null when neither exists.
     *
     * @since 2.9.0
     *
     * @return array{path: string, isBlade: bool}|null
     */
    protected function resolveThemeFile( string $theme, string $slug ): ?array
    {
        $directory = config( 'cms.themes.directory', 'themes' );
        $base      = base_path( $directory ) . '/' . $theme . '/parts/' . $slug;

        if ( File::exists( $base . '.html' ) ) {
            return ['path' => $base . '.html', 'isBlade' => false];
        }

        if ( File::exists( $base . '.blade.php' ) ) {
            return ['path' => $base . '.blade.php', 'isBlade' => true];
        }

        return null;
    }

    /**
     * @since 2.0.0
     *
     * @return array<int, string>
     */
    protected function themeFileSlugs( string $theme ): array
    {
        $directory = config( 'cms.themes.directory', 'themes' );
        $dir       = base_path( $directory ) . '/' . $theme . '/parts';

        if ( ! File::isDirectory( $dir ) ) {
            return [];
        }

        $slugs = [];

        foreach ( File::files( $dir ) as $file ) {
            $name = $file->getFilename();

            // `getFilenameWithoutExtension()` strips only the final extension,
            // so `header.blade.php` would yield `header.blade`; match the
            // compound `.blade.php` suffix explicitly before `.html`.
            if ( str_ends_with( $name, '.blade.php' ) ) {
                $slugs[] = substr( $name, 0, -strlen( '.blade.php' ) );
            } elseif ( 'html' === $file->getExtension() ) {
                $slugs[] = $file->getFilenameWithoutExtension();
            }
        }

        return array_values( array_unique( $slugs ) );
    }

    /**
     * @since 2.0.0
     */
    protected function humanizeSlug( string $slug ): string
    {
        return ucwords( str_replace( ['-', '_'], ' ', $slug ) );
    }

    /**
     * Guess a template-part area from the slug for theme-file parts that
     * carry no metadata: e.g. `header`, `header-large` → `'header'`,
     * `footer` → `'footer'`, anything else → `'uncategorized'`
     * (WP core's catch-all label, used for the Create-overlay action
     * on `core/navigation` — Keystone #55).
     *
     * @since 2.0.0
     */
    protected function guessAreaFromSlug( string $slug ): string
    {
        foreach ( ['header', 'footer', 'sidebar'] as $area ) {
            if ( $slug === $area || str_starts_with( $slug, $area . '-' ) ) {
                return $area;
            }
        }

        return 'uncategorized';
    }
}
