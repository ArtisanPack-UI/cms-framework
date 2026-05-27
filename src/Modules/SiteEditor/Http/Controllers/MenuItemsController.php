<?php

/**
 * Menu Items Controller
 *
 * Implements the WordPress `/wp/v2/menu-items` REST shape against the
 * cms-framework `MenuItem` model. Items are scoped to a parent `Menu`
 * via the `?menus={id}` query param on `index`; the `menus` field is
 * required on `store` and prohibited on `update` (items cannot be
 * reparented across menus — delete and recreate instead).
 *
 * All read and write operations are scoped to menus belonging to the
 * active theme — items in other themes' menus are unreachable through
 * this controller (404 instead of cross-theme leakage).
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests\MenuItemRequest;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources\MenuItemResource;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuItem;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @since 2.0.0
 */
#[Group( 'Site Editor / Menu Items', weight: 61 )]
class MenuItemsController extends Controller
{
    /**
     * @since 2.0.0
     */
    public function __construct(
        private ThemeManager $themeManager,
    ) {
    }

    /**
     * GET /api/v1/menu-items?menus={id} — list items for a menu, ordered
     * by `(COALESCE(parent_id, 0), position, id)` so consumers receive a
     * deterministic flat list ready to nest. The `COALESCE` normalizes
     * NULL ordering across SQL engines (MySQL/SQLite default NULLs first,
     * PostgreSQL defaults NULLs last); the `id` tiebreaker keeps ordering
     * stable when two siblings share `position`.
     *
     * @since 2.0.0
     */
    public function index( Request $request ): JsonResponse
    {
        $query = $this->themeScopedQuery();

        if ( null === $query ) {
            return response()->json( [] );
        }

        $query->orderByRaw( 'COALESCE(parent_id, 0)' )
            ->orderBy( 'position' )
            ->orderBy( 'id' );

        $menusFilter = $request->query( 'menus' );

        if ( null !== $menusFilter ) {
            if ( ! self::isPositiveIntegerString( $menusFilter ) ) {
                return response()->json( [
                    'message' => 'Invalid menus filter.',
                    'errors'  => ['menus' => ['menus must be a positive integer menu id.']],
                ], 422 );
            }

            $query->where( 'menu_id', (int) $menusFilter );
        }

        return response()->json( MenuItemResource::collection( $query->get() ) );
    }

    /**
     * GET /api/v1/menu-items/{id} — show a single menu item.
     *
     * @since 2.0.0
     */
    public function show( int $id ): JsonResponse
    {
        $item = $this->findItemForActiveTheme( $id );

        if ( null === $item ) {
            return response()->json( ['message' => 'Menu item not found.'], 404 );
        }

        return response()->json( MenuItemResource::toArray( $item ) );
    }

    /**
     * POST /api/v1/menu-items — create an item under a menu in the active
     * theme. Returns 404 when the menu belongs to another theme so this
     * endpoint cannot be used to write into other themes' menus.
     *
     * @since 2.0.0
     */
    public function store( MenuItemRequest $request ): JsonResponse
    {
        $theme = $this->activeThemeSlug();

        if ( null === $theme ) {
            return response()->json( ['message' => 'No active theme.'], 409 );
        }

        $validated = $request->validated();
        $menu      = Menu::query()
            ->where( 'theme', $theme )
            ->find( (int) $validated['menus'] );

        if ( null === $menu ) {
            return response()->json( ['message' => 'Menu not found.'], 404 );
        }

        $item = MenuItem::create( $this->mapPayload( $validated, $menu->id ) );

        return response()->json( MenuItemResource::toArray( $item ), 201 );
    }

    /**
     * PUT /api/v1/menu-items/{id} — update an item in the active theme.
     *
     * @since 2.0.0
     */
    public function update( MenuItemRequest $request, int $id ): JsonResponse
    {
        $item = $this->findItemForActiveTheme( $id );

        if ( null === $item ) {
            return response()->json( ['message' => 'Menu item not found.'], 404 );
        }

        $validated = $request->validated();

        $item->update( $this->mapPayload( $validated, (int) $item->menu_id, $item ) );

        return response()->json( MenuItemResource::toArray( $item->refresh() ) );
    }

    /**
     * DELETE /api/v1/menu-items/{id} — delete an item (cascades to
     * children).
     *
     * @since 2.0.0
     */
    public function destroy( int $id ): JsonResponse
    {
        $item = $this->findItemForActiveTheme( $id );

        if ( null === $item ) {
            return response()->json( ['message' => 'Menu item not found.'], 404 );
        }

        $item->delete();

        return response()->json( null, 204 );
    }

    /**
     * Build a base query that only sees items whose parent menu belongs to
     * the active theme. Returns null when there is no active theme so the
     * caller can short-circuit (typically by returning an empty list).
     *
     * @since 2.0.0
     */
    protected function themeScopedQuery(): ?Builder
    {
        $theme = $this->activeThemeSlug();

        if ( null === $theme ) {
            return null;
        }

        return MenuItem::query()->whereHas( 'menu', static function ( Builder $menu ) use ( $theme ): void {
            $menu->where( 'theme', $theme );
        } );
    }

    /**
     * Look up a single item, verifying its parent menu belongs to the
     * active theme.
     *
     * @since 2.0.0
     */
    protected function findItemForActiveTheme( int $id ): ?MenuItem
    {
        $query = $this->themeScopedQuery();

        if ( null === $query ) {
            return null;
        }

        return $query->find( $id );
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
     * Strict positive-integer-string check. Rejects:
     *
     * - `is_numeric` edge cases like `"1e3"` (would cast to 1, not 1000).
     * - `"0"` (menu ids are 1-based; rejecting at the boundary surfaces
     *   the bad input as 422 instead of an empty result set).
     * - Leading-zero strings like `"01"` (would cast to 1, masking the
     *   client's intent — surface as 422 so misformatted ids aren't
     *   silently coerced).
     *
     * @since 2.0.0
     */
    protected static function isPositiveIntegerString( mixed $value ): bool
    {
        return is_string( $value )
            && '' !== $value
            && ctype_digit( $value )
            && '0' !== $value[0];
    }

    /**
     * Translate the WP-shape request payload into model attributes.
     *
     * Only fields present in the validated payload are mapped — omitted
     * fields keep their existing values on update, matching WP REST PUT
     * semantics.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $validated
     *
     * @return array<string, mixed>
     */
    protected function mapPayload( array $validated, int $menuId, ?MenuItem $existing = null ): array
    {
        $attributes = ['menu_id' => $menuId];

        $map = [
            'title'       => 'label',
            'url'         => 'url',
            'target'      => 'target',
            'classes'     => 'classes',
            'description' => 'description',
            'menu_order'  => 'position',
            'parent'      => 'parent_id',
            'object'      => 'object_type',
            'object_id'   => 'object_id',
            'kind'        => 'kind',
            'type'        => 'type',
            'xfn'         => 'rel',
        ];

        foreach ( $map as $payloadKey => $modelKey ) {
            if ( array_key_exists( $payloadKey, $validated ) ) {
                $attributes[ $modelKey ] = $validated[ $payloadKey ];
            }
        }

        // WP-style payloads carry `parent: 0` to mean "no parent". The
        // model stores root items as `parent_id => null`, so coerce here
        // before the create/update — persisting a literal `0` would break
        // the self-referencing FK and the tree-traversal queries.
        if ( array_key_exists( 'parent_id', $attributes )
            && ( 0 === $attributes['parent_id'] || '0' === $attributes['parent_id'] ) ) {
            $attributes['parent_id'] = null;
        }

        if ( null === $existing && ! array_key_exists( 'position', $attributes ) ) {
            $attributes['position'] = 0;
        }

        if ( null === $existing && ! array_key_exists( 'target', $attributes ) ) {
            $attributes['target'] = '_self';
        }

        if ( null === $existing && ! array_key_exists( 'type', $attributes ) ) {
            $attributes['type'] = MenuItem::TYPE_LINK;
        }

        return $attributes;
    }
}
