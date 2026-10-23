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

        $manifest = $this->activeThemeManifest();

        if ( null === $manifest ) {
            return null;
        }

        $theme = (string) $manifest['slug'];

        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput.VariableNotSanitized -- $theme is the active theme slug (internal config); $slug is guarded by SlugValidator at the top of this method. Both are bound as Eloquent query parameters.
        $row = TemplatePart::query()->where( 'theme', $theme )->where( 'slug', $slug )->first();

        return $this->buildEntity( $manifest, $slug, $row, $this->resolveThemeFile( $theme, $slug ) );
    }

    /**
     * Lists every template part for the active theme with one query and one
     * scan of the theme's `parts/` directory, building each entity from the
     * row and file already in hand rather than re-resolving per slug (#424).
     *
     * @since 2.0.0
     * @since 2.15.6 Builds entities from the fetched rows and file index instead of calling resolve() per slug.
     *
     * @return array<int, ResolvedEntity>
     */
    public function all(): array
    {
        $manifest = $this->activeThemeManifest();

        if ( null === $manifest ) {
            return [];
        }

        $theme      = (string) $manifest['slug'];
        $themeFiles = $this->themeFileIndex( $theme );
        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput.VariableNotSanitized -- $theme is the active theme slug from ThemeManager (internal config, not request input) and is bound as an Eloquent query parameter.
        $rows       = TemplatePart::query()->where( 'theme', $theme )->get()->keyBy( 'slug' );

        // Numeric slugs become integer array keys; cast back so the
        // string-typed slug guard and builder accept them.
        $allSlugs = array_map( 'strval', array_unique( array_merge( array_keys( $themeFiles ), $rows->keys()->all() ) ) );
        sort( $allSlugs );

        $resolved = [];

        foreach ( $allSlugs as $slug ) {
            if ( ! $this->isValidSlug( $slug ) ) {
                continue;
            }

            $entity = $this->buildEntity( $manifest, $slug, $rows->get( $slug ), $themeFiles[ $slug ] ?? null );

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
        $theme = $this->activeThemeManifest();

        return null !== $theme ? (string) $theme['slug'] : null;
    }

    /**
     * Active-theme manifest accessor. Returns null when no theme is active or
     * the active theme has no `slug` key in its manifest.
     *
     * @since 2.15.3
     *
     * @return array<string, mixed>|null
     */
    protected function activeThemeManifest(): ?array
    {
        $theme = $this->themeManager->getActiveTheme();

        return null !== $theme && ! empty( $theme['slug'] ) ? $theme : null;
    }

    /**
     * Build the entity for a part slug from its DB row (if any) and theme
     * file (if any). The DB row wins; otherwise the theme file supplies the
     * entity, with its area taken from the theme manifest. Returns null when
     * neither exists.
     *
     * Shared by {@see resolve()} and {@see all()} so the listing path can
     * reuse the rows and file index it already fetched.
     *
     * @since 2.15.6
     *
     * @param  array<string, mixed>                    $manifest   Active theme's parsed `theme.json`.
     * @param  string                                  $slug       Validated part slug.
     * @param  TemplatePart|null                       $row        The DB override for the slug, if any.
     * @param  array{path: string, isBlade: bool}|null  $themeFile  The theme file backing the slug, if any.
     */
    protected function buildEntity( array $manifest, string $slug, ?TemplatePart $row, ?array $themeFile ): ?ResolvedEntity
    {
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

        $theme = (string) $manifest['slug'];

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
                area         : $this->themeFileArea( $manifest, $slug ),
                model        : null,
            );
        }

        $markup = File::get( $themeFile['path'] );

        return new ResolvedEntity(
            slug         : $slug,
            theme        : $theme,
            source       : 'theme',
            raw          : $markup,
            blocks       : ThemeFileBlockParser::parseCached( $markup ),
            title        : $this->humanizeSlug( $slug ),
            description  : null,
            status       : 'publish',
            hasThemeFile : true,
            isCustom     : false,
            isBlade      : false,
            area         : $this->themeFileArea( $manifest, $slug ),
            model        : null,
        );
    }

    /**
     * Area for a theme-file part: the `area` declared for the slug in the
     * active theme's `theme.json` `templateParts` (matched on `name`), so a
     * theme can ship e.g. a `navigation-overlay` part without a DB row.
     * Falls back to {@see guessAreaFromSlug()} when the theme declares no
     * entry, or an entry with no usable `area`.
     *
     * @since 2.15.3
     *
     * @param  array<string, mixed>  $manifest  Active theme's parsed `theme.json`.
     */
    protected function themeFileArea( array $manifest, string $slug ): string
    {
        $templateParts = $manifest['templateParts'] ?? null;

        if ( is_array( $templateParts ) ) {
            foreach ( $templateParts as $templatePart ) {
                if ( ! is_array( $templatePart ) || ( $templatePart['name'] ?? null ) !== $slug ) {
                    continue;
                }

                $area = $templatePart['area'] ?? null;

                if ( is_string( $area ) && '' !== trim( $area ) ) {
                    return trim( $area );
                }

                break;
            }
        }

        return $this->guessAreaFromSlug( $slug );
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
        return array_map( 'strval', array_keys( $this->themeFileIndex( $theme ) ) );
    }

    /**
     * Map each part slug the active theme provides on disk to its backing
     * file, from a single directory scan. Applies the same precedence as
     * {@see resolveThemeFile()}: HTML wins over Blade for the same slug.
     *
     * @since 2.15.6
     *
     * @return array<string, array{path: string, isBlade: bool}>
     */
    protected function themeFileIndex( string $theme ): array
    {
        $directory = config( 'cms.themes.directory', 'themes' );
        $dir       = base_path( $directory ) . '/' . $theme . '/parts';

        if ( ! File::isDirectory( $dir ) ) {
            return [];
        }

        $index = [];

        foreach ( File::files( $dir ) as $file ) {
            $name = $file->getFilename();
            $path = $dir . '/' . $name;

            // `getFilenameWithoutExtension()` strips only the final extension,
            // so `header.blade.php` would yield `header.blade`; match the
            // compound `.blade.php` suffix explicitly before `.html`.
            if ( str_ends_with( $name, '.blade.php' ) ) {
                $slug           = substr( $name, 0, -strlen( '.blade.php' ) );
                $index[ $slug ] ??= ['path' => $path, 'isBlade' => true];
            } elseif ( 'html' === $file->getExtension() ) {
                $index[ $file->getFilenameWithoutExtension() ] = ['path' => $path, 'isBlade' => false];
            }
        }

        return $index;
    }

    /**
     * @since 2.0.0
     */
    protected function humanizeSlug( string $slug ): string
    {
        return ucwords( str_replace( ['-', '_'], ' ', $slug ) );
    }

    /**
     * Guess a template-part area from the slug for theme-file parts whose
     * theme declares no `templateParts` area: e.g. `header`, `header-large` → `'header'`,
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
