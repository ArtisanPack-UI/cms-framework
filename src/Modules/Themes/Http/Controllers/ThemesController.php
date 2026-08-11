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
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeUpdateException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions\ThemeValidationException;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\UpdateManager;
use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

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
     * @param  UpdateManager  $updateManager  Theme update manager instance.
     */
    public function __construct(
        private ThemeManager $themeManager,
        private UpdateManager $updateManager,
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
     * Lists the installed themes that have an update available.
     *
     * Each entry is keyed by theme slug and carries the same shape the plugins
     * update endpoint returns, so a host admin UI can render both extension
     * types through one component. Themes that declare no `update` source in
     * their theme.json, or that are already current, are omitted.
     *
     * Endpoint: GET /v1/themes/updates
     *
     * @since 2.8.0
     *
     * @return JsonResponse JSON response with the available updates keyed by slug.
     */
    public function checkUpdates(): JsonResponse
    {
        return response()->json( [
            'updates' => $this->updateManager->checkForUpdates(),
        ] );
    }

    /**
     * Updates an installed theme to its latest published version.
     *
     * Failures surface as a `ValidationException` rather than a bare JSON
     * error body, so host apps using Inertia get a working error bag instead
     * of an unhandled response.
     *
     * Endpoint: POST /v1/themes/{slug}/update
     *
     * @since 2.8.0
     *
     * @param  string  $slug  Theme slug identifier.
     *
     * @throws ValidationException If the update fails.
     *
     * @return JsonResponse JSON response reporting whether an update was installed.
     */
    public function update( string $slug ): JsonResponse
    {
        try {
            $updated = $this->updateManager->updateTheme( $slug );
        } catch ( ThemeNotFoundException ) {
            return response()->json( [
                'message' => __( 'Theme ":slug" not found.', ['slug' => $slug] ),
            ], 404 );
        } catch ( ThemeUpdateException $e ) {
            // `UpdateManager` funnels every in-flight failure — corrupt archive,
            // checksum mismatch, failed swap — through this type, carrying the
            // underlying reason verbatim.
            throw ValidationException::withMessages( [
                'slug' => $e->getMessage(),
            ] );
        } catch ( Exception $e ) {
            report( $e );

            throw ValidationException::withMessages( [
                'slug' => __( 'An unexpected error occurred while updating the theme.' ),
            ] );
        }

        return response()->json( [
            'message' => $updated
                ? __( 'Theme updated successfully.' )
                : __( 'Theme is already up to date.' ),
            'updated' => $updated,
            'theme'   => $this->themeManager->getTheme( $slug ),
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
