<?php

/**
 * Menu Locations Controller
 *
 * `GET /api/v1/menu-locations` — list theme-declared locations + their
 * current assignments. `PUT /api/v1/menu-locations/{location}` — assign
 * a menu. `DELETE /api/v1/menu-locations/{location}` — unassign.
 *
 * Locations are resolved through {@see Menus::locations()} (app config +
 * theme.json `menus.locations`, theme wins on collision).
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests\MenuLocationAssignmentRequest;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources\MenuLocationResource;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuLocationAssignment;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\Menus;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * @since 1.2.0
 */
#[Group('Site Editor / Menu Locations', weight: 62)]
class MenuLocationsController extends Controller
{
    /**
     * @since 1.2.0
     */
    public function __construct(
        private ThemeManager $themeManager,
    ) {}

    /**
     * GET /api/v1/menu-locations — list locations + assignments.
     *
     * @since 1.2.0
     */
    public function index(): JsonResponse
    {
        $locations = Menus::locations();

        $theme = $this->activeThemeSlug();

        $assignments = null !== $theme
            ? MenuLocationAssignment::query()
                ->where('theme', $theme)
                ->pluck('menu_id', 'location')
                ->map(static fn ($id): int => (int) $id)
                ->all()
            : [];

        return response()->json(MenuLocationResource::collection($locations, $assignments));
    }

    /**
     * PUT /api/v1/menu-locations/{location} — assign a menu.
     *
     * @since 1.2.0
     */
    public function update(MenuLocationAssignmentRequest $request, string $location): JsonResponse
    {
        $theme = $this->activeThemeSlug();

        if (null === $theme) {
            return response()->json(['message' => 'No active theme.'], 409);
        }

        $locations = Menus::locations();

        if (! array_key_exists($location, $locations)) {
            return response()->json([
                'message' => 'Location is not declared by the active theme or app config.',
            ], 404);
        }

        $menuId = (int) $request->validated()['menu'];
        $menu   = Menu::query()->where('theme', $theme)->find($menuId);

        if (null === $menu) {
            return response()->json([
                'message' => 'Menu does not belong to the active theme.',
                'errors'  => ['menu' => ['Menu must be authored against the active theme.']],
            ], 422);
        }

        Menus::assign($location, $menuId);

        return response()->json(
            MenuLocationResource::toArray($location, $locations[$location], $menuId),
        );
    }

    /**
     * DELETE /api/v1/menu-locations/{location} — unassign.
     *
     * @since 1.2.0
     */
    public function destroy(string $location): JsonResponse
    {
        $deleted = Menus::unassign($location);

        if (! $deleted) {
            return response()->json(['message' => 'No assignment to unassign.'], 404);
        }

        return response()->json(null, 204);
    }

    /**
     * @since 1.2.0
     */
    protected function activeThemeSlug(): ?string
    {
        $theme = $this->themeManager->getActiveTheme();

        return null !== $theme && ! empty($theme['slug']) ? (string) $theme['slug'] : null;
    }
}
