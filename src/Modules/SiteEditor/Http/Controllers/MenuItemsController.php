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
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests\MenuItemRequest;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources\MenuItemResource;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuItem;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @since 1.2.0
 */
#[Group( 'Site Editor / Menu Items', weight: 61 )]
class MenuItemsController extends Controller
{
    /**
     * GET /api/v1/menu-items?menus={id} — list items for a menu, ordered
     * by `(parent_id, position)` so consumers receive a deterministic flat
     * list ready to nest.
     *
     * @since 1.2.0
     */
    public function index( Request $request ): JsonResponse
    {
        $query = MenuItem::query()
            ->orderBy( 'parent_id' )
            ->orderBy( 'position' );

        $menusFilter = $request->query( 'menus' );

        if ( null !== $menusFilter ) {
            if ( ! is_numeric( $menusFilter ) ) {
                return response()->json( [
                    'message' => 'Invalid menus filter.',
                    'errors'  => [ 'menus' => [ 'menus must be an integer menu id.' ] ],
                ], 422 );
            }

            $query->where( 'menu_id', (int) $menusFilter );
        }

        return response()->json( MenuItemResource::collection( $query->get() ) );
    }

    /**
     * GET /api/v1/menu-items/{id} — show a single menu item.
     *
     * @since 1.2.0
     */
    public function show( int $id ): JsonResponse
    {
        $item = MenuItem::query()->find( $id );

        if ( null === $item ) {
            return response()->json( [ 'message' => 'Menu item not found.' ], 404 );
        }

        return response()->json( MenuItemResource::toArray( $item ) );
    }

    /**
     * POST /api/v1/menu-items — create an item under a menu.
     *
     * @since 1.2.0
     */
    public function store( MenuItemRequest $request ): JsonResponse
    {
        $validated = $request->validated();

        $menu = Menu::query()->find( (int) $validated['menus'] );

        if ( null === $menu ) {
            return response()->json( [ 'message' => 'Menu not found.' ], 404 );
        }

        $item = MenuItem::create( $this->mapPayload( $validated, $menu->id ) );

        return response()->json( MenuItemResource::toArray( $item ), 201 );
    }

    /**
     * PUT /api/v1/menu-items/{id} — update an item.
     *
     * @since 1.2.0
     */
    public function update( MenuItemRequest $request, int $id ): JsonResponse
    {
        $item = MenuItem::query()->find( $id );

        if ( null === $item ) {
            return response()->json( [ 'message' => 'Menu item not found.' ], 404 );
        }

        $validated = $request->validated();

        $item->update( $this->mapPayload( $validated, (int) $item->menu_id, $item ) );

        return response()->json( MenuItemResource::toArray( $item->refresh() ) );
    }

    /**
     * DELETE /api/v1/menu-items/{id} — delete an item (cascades to
     * children).
     *
     * @since 1.2.0
     */
    public function destroy( int $id ): JsonResponse
    {
        $item = MenuItem::query()->find( $id );

        if ( null === $item ) {
            return response()->json( [ 'message' => 'Menu item not found.' ], 404 );
        }

        $item->delete();

        return response()->json( null, 204 );
    }

    /**
     * Translate the WP-shape request payload into model attributes.
     *
     * Only fields present in the validated payload are mapped — omitted
     * fields keep their existing values on update, matching WP REST PUT
     * semantics.
     *
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $validated
     *
     * @return array<string, mixed>
     */
    protected function mapPayload( array $validated, int $menuId, ?MenuItem $existing = null ): array
    {
        $attributes = [ 'menu_id' => $menuId ];

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
