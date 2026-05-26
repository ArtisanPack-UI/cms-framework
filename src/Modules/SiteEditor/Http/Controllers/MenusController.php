<?php

/**
 * Menus Controller
 *
 * Implements the WordPress `/wp/v2/menus` REST shape against the
 * cms-framework `Menu` model. Menus are theme-scoped — listing returns
 * only menus authored against the active theme.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests\MenuRequest;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources\MenuResource;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\SlugValidator;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * @since 2.0.0
 */
#[Group( 'Site Editor / Menus', weight: 60 )]
class MenusController extends Controller
{
    /**
     * @since 2.0.0
     */
    public function __construct(
        private ThemeManager $themeManager,
    ) {
    }

    /**
     * GET /api/v1/menus — list menus for the active theme.
     *
     * @since 2.0.0
     */
    public function index(): JsonResponse
    {
        $theme = $this->activeThemeSlug();

        if ( null === $theme ) {
            return response()->json( [] );
        }

        $menus = Menu::query()
            ->where( 'theme', $theme )
            ->with( 'locationAssignments' )
            ->orderBy( 'name' )
            ->get();

        return response()->json( MenuResource::collection( $menus ) );
    }

    /**
     * GET /api/v1/menus/{id_or_slug} — show a single menu.
     *
     * @since 2.0.0
     */
    public function show( string $idOrSlug ): JsonResponse
    {
        $menu = $this->resolveMenu( $idOrSlug );

        if ( null === $menu ) {
            return response()->json( ['message' => 'Menu not found.'], 404 );
        }

        return response()->json( MenuResource::toArray( $menu ) );
    }

    /**
     * POST /api/v1/menus — create a menu against the active theme.
     *
     * @since 2.0.0
     */
    public function store( MenuRequest $request ): JsonResponse
    {
        $theme = $this->activeThemeSlug();

        if ( null === $theme ) {
            return response()->json( ['message' => 'No active theme.'], 409 );
        }

        $validated = $request->validated();

        try {
            $menu = Menu::create( $this->normalizeAttributes( $theme, $validated ) );
        } catch ( QueryException $e ) {
            if ( $this->isUniqueViolation( $e ) ) {
                return response()->json( [
                    'message' => 'A menu with this slug already exists for the active theme.',
                    'errors'  => ['slug' => ['Slug must be unique within the theme.']],
                ], 409 );
            }

            throw $e;
        }

        $menu->load( 'locationAssignments' );

        return response()->json( MenuResource::toArray( $menu ), 201 );
    }

    /**
     * PUT /api/v1/menus/{id_or_slug} — update menu metadata.
     *
     * @since 2.0.0
     */
    public function update( MenuRequest $request, string $idOrSlug ): JsonResponse
    {
        $menu = $this->resolveMenu( $idOrSlug );

        if ( null === $menu ) {
            return response()->json( ['message' => 'Menu not found.'], 404 );
        }

        $validated = $request->validated();

        if ( array_key_exists( 'slug', $validated ) && $validated['slug'] !== $menu->slug ) {
            return response()->json( [
                'message' => 'Body slug does not match the menu slug.',
                'errors'  => ['slug' => ['Slug renames are not supported.']],
            ], 422 );
        }

        unset( $validated['slug'] );

        $menu->update( $validated );

        $menu->load( 'locationAssignments' );

        return response()->json( MenuResource::toArray( $menu->refresh() ) );
    }

    /**
     * DELETE /api/v1/menus/{id_or_slug} — delete the menu (cascades to
     * items and assignments).
     *
     * @since 2.0.0
     */
    public function destroy( string $idOrSlug ): JsonResponse
    {
        $menu = $this->resolveMenu( $idOrSlug );

        if ( null === $menu ) {
            return response()->json( ['message' => 'Menu not found.'], 404 );
        }

        $menu->delete();

        return response()->json( null, 204 );
    }

    /**
     * Resolve `{id_or_slug}` to a {@see Menu} for the active theme.
     *
     * Tries slug match first, then falls back to integer-id lookup. Slug-first
     * order matters for numeric-only slugs (e.g. `"123"`), which `SlugValidator`
     * accepts and which would otherwise be unreachable: an ID-first resolver
     * would always shadow them with the row whose `id = 123`, which may belong
     * to a different menu (or another theme).
     *
     * @since 2.0.0
     */
    protected function resolveMenu( string $idOrSlug ): ?Menu
    {
        $theme = $this->activeThemeSlug();

        if ( null === $theme ) {
            return null;
        }

        $query = Menu::query()->where( 'theme', $theme )->with( 'locationAssignments' );

        if ( SlugValidator::isValid( $idOrSlug ) ) {
            $bySlug = ( clone $query )->where( 'slug', $idOrSlug )->first();

            if ( null !== $bySlug ) {
                return $bySlug;
            }
        }

        if ( ctype_digit( $idOrSlug ) ) {
            return $query->find( (int) $idOrSlug );
        }

        return null;
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
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $validated
     *
     * @return array<string, mixed>
     */
    protected function normalizeAttributes( string $theme, array $validated ): array
    {
        $validated['theme']     = $theme;
        $validated['author_id'] = $validated['author_id'] ?? auth()->id();

        if ( ! array_key_exists( 'auto_add_pages', $validated ) ) {
            $validated['auto_add_pages'] = false;
        }

        return $validated;
    }

    /**
     * @since 2.0.0
     */
    protected function isUniqueViolation( QueryException $e ): bool
    {
        $sqlState = $e->getCode();

        if ( '23000' === $sqlState || '23505' === $sqlState) {
            return true;
        }

        return str_contains( strtolower( $e->getMessage()), 'unique');
    }
}
