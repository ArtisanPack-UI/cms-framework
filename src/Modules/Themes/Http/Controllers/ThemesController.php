<?php

/**
 * Themes API Controller
 *
 * Handles HTTP requests for theme management operations.
 *
 * @since      1.0.0
 */

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Themes Controller class.
 *
 * Provides REST API endpoints for theme operations:
 * - Listing all available themes
 * - Retrieving a specific theme
 * - Activating a theme
 *
 * @since 1.0.0
 */
class ThemesController extends Controller
{
    /**
     * Constructs the ThemesController instance.
     *
     * @since 1.0.0
     *
     * @param  ThemeManager  $themeManager  Theme manager instance.
     */
    public function __construct(
        private ThemeManager $themeManager,
    ) {
    }

    /**
     * Lists all available themes.
     *
     * Returns a JSON response containing all discovered themes and the
     * currently active theme slug.
     *
     * Endpoint: GET /api/v1/themes
     *
     * @since 1.0.0
     *
     * @return JsonResponse JSON response with themes array and active theme slug.
     */
    public function index(): JsonResponse
    {
        $themes      = $this->themeManager->discoverThemes();
        $activeTheme = $this->themeManager->getActiveTheme();

        return response()->json( [
            'themes' => $themes,
            'active' => $activeTheme['slug'] ?? null,
        ] );
    }

    /**
     * Gets a specific theme by slug.
     *
     * Returns the theme manifest data for the requested theme, or a 404
     * error if the theme is not found.
     *
     * Endpoint: GET /api/v1/themes/{slug}
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Theme slug identifier.
     *
     * @return JsonResponse JSON response with theme data or error message.
     */
    public function show( string $slug ): JsonResponse
    {
        $theme = $this->themeManager->getTheme( $slug );

        if ( ! $theme ) {
            return response()->json( [
                'message' => 'Theme not found',
            ], 404 );
        }

        return response()->json( $theme );
    }

    /**
     * Activates a theme.
     *
     * Sets the specified theme as the active theme and clears relevant caches.
     * Returns a success message with the activated theme data, or an error
     * message if activation fails.
     *
     * Endpoint: POST /api/v1/themes/{slug}/activate
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Theme slug identifier.
     *
     * @return JsonResponse JSON response with success message and theme data, or error.
     */
    public function activate( string $slug ): JsonResponse
    {
        try {
            $success = $this->themeManager->activateTheme( $slug );

            return response()->json( [
                'message' => 'Theme activated successfully',
                'theme'   => $this->themeManager->getTheme( $slug ),
            ] );
        } catch ( Exception $e ) {
            return response()->json( [
                'message' => $e->getMessage(),
            ], 400 );
        }
    }
}
