<?php

/**
 * Menu Resolver
 *
 * Resolves theme-declared menu *locations* into the navigation shape that
 * visual-editor's `ap.visual-editor.navigation` filter consumes:
 * `array<string, ResolvedMenu>` keyed by location.
 *
 * Unlike H1/H2/H3, this resolver has no theme-file fallback — menus are
 * DB-only per plan 14 §4.2. Themes contribute *location names* via
 * `theme.json` `menus.locations`; menus and items live entirely in the
 * database. Locations the active theme declares but no app has assigned a
 * menu to still appear in the output with `wp_id = null` so editor
 * surfaces can render empty slots.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuItem;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuLocationAssignment;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\Menus;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;

/**
 * @since 2.0.0
 */
class MenuResolver
{
    /**
     * @since 2.0.0
     */
    public function __construct(
        private ThemeManager $themeManager,
    ) {
    }

    /**
     * Returns every theme-declared location for the active theme keyed by
     * location key. Locations without an assigned menu still appear with
     * `wp_id => null` and an empty `items` array.
     *
     * @since 2.0.0
     *
     * @return array<string, array{location: string, name: string, items: array<int, array<string, mixed>>, wp_id: int|null}>
     */
    public function all(): array
    {
        $theme = $this->activeThemeSlug();

        if ( null === $theme ) {
            return [];
        }

        $locations = Menus::locations();

        if ( empty( $locations ) ) {
            return [];
        }

        $assignments = MenuLocationAssignment::query()
            // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput.VariableNotSanitized -- $theme is the active theme slug from ThemeManager (internal config, not request input) and is bound as an Eloquent query parameter.
            ->where( 'theme', $theme )
            ->with( ['menu', 'menu.items'] )
            ->get()
            ->keyBy( 'location' );

        $resolved = [];

        foreach ( $locations as $location => $label ) {
            $assignment = $assignments->get( $location );
            $menu       = null !== $assignment ? $assignment->menu : null;

            $resolved[ $location ] = [
                'location' => $location,
                'name'     => null !== $menu ? $menu->name : $label,
                'items'    => null !== $menu ? $this->projectItems( $menu->items->all() ) : [],
                'wp_id'    => null !== $menu ? (int) $menu->id : null,
            ];
        }

        return $resolved;
    }

    /**
     * Resolve a single location for the active theme. Returns null when
     * the location key isn't declared (by app config or theme).
     *
     * @since 2.0.0
     *
     * @return array{location: string, name: string, items: array<int, array<string, mixed>>, wp_id: int|null}|null
     */
    public function resolve( string $location ): ?array
    {
        $all = $this->all();

        return $all[ $location ] ?? null;
    }

    /**
     * Revert (unassign) a location for the active theme. The underlying
     * `Menu` is preserved — only the `MenuLocationAssignment` row is
     * deleted.
     *
     * @since 2.0.0
     */
    public function revert( string $location ): bool
    {
        return Menus::unassign( $location );
    }

    /**
     * Project a flat list of `MenuItem` rows into the upstream
     * `core/navigation-link` / `core/navigation-submenu` / `core/page-list`
     * shapes, with children nested under parents by `(parent_id, position)`.
     *
     * Page-list items render as a dynamic placeholder (`dynamic: 'page-list'`)
     * — the resolver does not enumerate pages here so cms-framework stays
     * decoupled from Phase G content types. The navigation block performs
     * the page enumeration at render time.
     *
     * @since 2.0.0
     *
     * @param  array<int, MenuItem>  $items
     *
     * @return array<int, array<string, mixed>>
     */
    protected function projectItems( array $items ): array
    {
        $byParent = [];

        foreach ( $items as $item ) {
            $byParent[ (int) ( $item->parent_id ?? 0 ) ][] = $item;
        }

        // Stable order within each parent bucket — items are pre-ordered by the
        // Menu::items() relation, so rebuilding the tree just walks `$byParent`.
        return $this->buildBranch( $byParent, 0 );
    }

    /**
     * Recursive nesting helper for {@see projectItems()}.
     *
     * @since 2.0.0
     *
     * @param  array<int, array<int, MenuItem>>  $byParent
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildBranch( array $byParent, int $parentId ): array
    {
        if ( ! isset( $byParent[ $parentId ] ) ) {
            return [];
        }

        $branch = [];

        foreach ( $byParent[ $parentId ] as $item ) {
            $branch[] = $this->itemToShape( $item, $byParent );
        }

        return $branch;
    }

    /**
     * Convert one `MenuItem` row into the upstream navigation shape, recursing
     * into children for `submenu` items.
     *
     * @since 2.0.0
     *
     * @param  array<int, array<int, MenuItem>>  $byParent
     *
     * @return array<string, mixed>
     */
    protected function itemToShape( MenuItem $item, array $byParent ): array
    {
        $shape = [
            'id'          => (int) $item->id,
            'type'        => $item->type,
            'label'       => $item->label,
            'url'         => $item->url,
            'target'      => $item->target,
            'rel'         => $item->rel,
            'classes'     => $item->classes,
            'description' => $item->description,
            'object_type' => $item->object_type,
            'object_id'   => null !== $item->object_id ? (int) $item->object_id : null,
            'kind'        => $item->kind,
            'parent_id'   => null !== $item->parent_id ? (int) $item->parent_id : null,
            'position'    => (int) $item->position,
        ];

        if ( MenuItem::TYPE_SUBMENU === $item->type ) {
            $shape['children'] = $this->buildBranch( $byParent, (int) $item->id );
        }

        if ( MenuItem::TYPE_PAGE_LIST === $item->type ) {
            $shape['dynamic'] = MenuItem::TYPE_PAGE_LIST;
        }

        return $shape;
    }

    /**
     * @since 2.0.0
     */
    protected function activeThemeSlug(): ?string
    {
        $theme = $this->themeManager->getActiveTheme();

        return null !== $theme && ! empty( $theme['slug'] ) ? (string) $theme['slug'] : null;
    }
}
