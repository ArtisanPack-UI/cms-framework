<?php

/**
 * Themes API Controller
 *
 * Handles HTTP requests for theme management operations.
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeInstallationException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeNotFoundException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeValidationException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
#[Group( 'Themes', weight: 16 )]
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
                'message' => __( 'Theme not found.' ),
            ], 404 );
        }

        return response()->json( $theme );
    }

    /**
     * Uploads and installs a theme from a ZIP archive.
     *
     * Accepts a multipart upload, validates the ZIP, extracts it into the
     * themes directory, and returns the parsed manifest of the newly installed
     * theme. Mirrors the Plugins module's install endpoint, with theme-named
     * exceptions and config keys plus a ZIP-slip guard during extraction.
     *
     * Endpoint: POST /v1/themes
     *
     * @since 2.0.0
     *
     * @param  Request  $request  Incoming request carrying the uploaded theme_zip.
     *
     * @return JsonResponse JSON response with the installed theme manifest, or an error.
     */
    public function upload( Request $request ): JsonResponse
    {
        $request->validate( [
            'theme_zip' => 'required|file|mimes:zip|max:' . (int) ( config( 'cms.themes.maxUploadSize', 10 * 1024 * 1024 ) / 1024 ),
        ] );

        try {
            $zipPath  = $request->file( 'theme_zip' )->path();
            $manifest = $this->themeManager->installFromZip( $zipPath );

            return response()->json( [
                'message' => __( 'Theme installed successfully.' ),
                'theme'   => $manifest,
            ], 201 );
        } catch ( ThemeValidationException|ThemeInstallationException $e ) {
            return response()->json( [
                'message' => $e->getMessage(),
            ], 422 );
        } catch ( Exception $e ) {
            report( $e );

            return response()->json( [
                'message' => __( 'An unexpected error occurred while installing the theme.' ),
            ], 500 );
        }
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
            $this->themeManager->activateTheme( $slug );

            return response()->json( [
                'message' => __( 'Theme activated successfully.' ),
                'theme'   => $this->themeManager->getTheme( $slug ),
            ] );
        } catch ( ThemeNotFoundException ) {
            return response()->json( [
                'message' => __( 'Theme ":slug" not found.', ['slug' => $slug] ),
            ], 404 );
        } catch ( Exception $e ) {
            report( $e );

            return response()->json( [
                'message' => __( 'An unexpected error occurred while activating the theme.' ),
            ], 500 );
        }
    }
}
