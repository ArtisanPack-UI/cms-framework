<?php

/**
 * Plugins API Controller
 *
 * Handles HTTP requests for plugin management operations.
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\CircularDependencyException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\DependencyNotSatisfiedException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\IncompatiblePluginException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginConflictException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginInstallationException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginNotFoundException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginUpdateException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginValidationException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Http\Requests\InstallPluginRequest;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\UpdateManager;
use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * Plugins Controller class.
 *
 * Every failure path other than the host-compatibility gate surfaces as a
 * `ValidationException`, so consumers get a 422 with a field-keyed `errors`
 * bag — the shape Inertia's error bag reads and the shape a pure-API client
 * can parse without a bespoke adapter.
 *
 * @since 1.0.0
 */
#[Group( 'Plugins', weight: 15 )]
class PluginsController extends Controller
{
    /**
     * Constructs the PluginsController instance.
     *
     * @since 1.0.0
     *
     * @param  PluginManager  $pluginManager  Plugin manager instance.
     * @param  UpdateManager  $updateManager  Plugin update manager instance.
     */
    public function __construct(
        private PluginManager $pluginManager,
        private UpdateManager $updateManager,
    ) {
    }

    /**
     * GET /api/v1/plugins
     * List all discovered plugins.
     */
    public function index(): JsonResponse
    {
        $plugins = $this->pluginManager->discoverPlugins();

        return response()->json( [
            'plugins' => $plugins,
        ] );
    }

    /**
     * GET /api/v1/plugins/{slug}
     * Get specific plugin details.
     */
    public function show( string $slug ): JsonResponse
    {
        $plugin = $this->pluginManager->getPlugin( $slug );

        if ( ! $plugin ) {
            return response()->json( [
                'message' => __( 'Plugin not found' ),
            ], 404 );
        }

        return response()->json( [
            'plugin' => $plugin,
        ] );
    }

    /**
     * Uploads and installs a plugin from a ZIP archive.
     *
     * Endpoint: POST /api/v1/plugins/install
     *
     * @since 1.0.0
     *
     * @param  InstallPluginRequest  $request  Validated request carrying the uploaded plugin_zip.
     *
     * @throws ValidationException If the archive is rejected or installation fails.
     *
     * @return JsonResponse JSON response with the installed plugin.
     */
    public function install( InstallPluginRequest $request ): JsonResponse
    {
        try {
            $zipPath = $request->file( 'plugin_zip' )->path();
            $plugin  = $this->pluginManager->installFromZip( $zipPath );
        } catch ( PluginValidationException|PluginInstallationException $e ) {
            throw ValidationException::withMessages( [
                'plugin_zip' => $e->getMessage(),
            ] );
        } catch ( Exception $e ) {
            report( $e );

            throw ValidationException::withMessages( [
                'plugin_zip' => __( 'An unexpected error occurred while installing the plugin.' ),
            ] );
        }

        return response()->json( [
            'message' => __( 'Plugin installed successfully' ),
            'plugin'  => $plugin,
        ], 201 );
    }

    /**
     * Activates an installed plugin.
     *
     * A plugin that declares a `min_host_version` the host does not satisfy
     * keeps its dedicated 409 response, whose structured payload carries the
     * version numbers a generic error bag cannot express.
     *
     * Endpoint: POST /api/v1/plugins/{slug}/activate
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Plugin slug identifier.
     *
     * @throws ValidationException If the plugin is unknown or activation fails.
     *
     * @return JsonResponse JSON response with a success message.
     */
    public function activate( string $slug ): JsonResponse
    {
        try {
            $this->pluginManager->activate( $slug );
        } catch ( IncompatiblePluginException $e ) {
            return response()->json( [
                'message'          => $e->getMessage(),
                'code'             => 'plugin_incompatible',
                'plugin'           => $e->pluginSlug,
                'required_version' => $e->requiredVersion,
                'host_version'     => $e->hostVersion,
            ], 409 );
        } catch ( PluginConflictException $e ) {
            return response()->json( [
                'message'   => $e->getMessage(),
                'code'      => 'plugin_conflict',
                'plugin'    => $e->pluginSlug,
                'conflicts' => $e->conflicts,
            ], 409 );
        } catch ( DependencyNotSatisfiedException $e ) {
            return response()->json( [
                'message'      => $e->getMessage(),
                'code'         => 'plugin_dependencies_unsatisfied',
                'plugin'       => $e->pluginSlug,
                'dependencies' => $e->result?->toArray(),
            ], 409 );
        } catch ( PluginNotFoundException $e ) {
            throw ValidationException::withMessages( [
                'slug' => $e->getMessage(),
            ] );
        } catch ( Exception $e ) {
            report( $e );

            throw ValidationException::withMessages( [
                'slug' => __( 'An unexpected error occurred while activating the plugin.' ),
            ] );
        }

        return response()->json( [
            'message' => __( 'Plugin activated successfully' ),
        ] );
    }

    /**
     * Deactivates an active plugin.
     *
     * Endpoint: POST /api/v1/plugins/{slug}/deactivate
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Plugin slug identifier.
     *
     * @throws ValidationException If the plugin is unknown or deactivation fails.
     *
     * @return JsonResponse JSON response with a success message.
     */
    public function deactivate( string $slug ): JsonResponse
    {
        try {
            $this->pluginManager->deactivate( $slug );
        } catch ( DependencyNotSatisfiedException $e ) {
            return response()->json( [
                'message'    => $e->getMessage(),
                'code'       => 'plugin_has_active_dependents',
                'plugin'     => $e->pluginSlug,
                'dependents' => $e->dependents,
            ], 409 );
        } catch ( PluginNotFoundException $e ) {
            throw ValidationException::withMessages( [
                'slug' => $e->getMessage(),
            ] );
        } catch ( Exception $e ) {
            report( $e );

            throw ValidationException::withMessages( [
                'slug' => __( 'An unexpected error occurred while deactivating the plugin.' ),
            ] );
        }

        return response()->json( [
            'message' => __( 'Plugin deactivated successfully' ),
        ] );
    }

    /**
     * Deletes an installed plugin.
     *
     * Endpoint: DELETE /api/v1/plugins/{slug}
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Plugin slug identifier.
     *
     * @throws ValidationException If the plugin is unknown or deletion fails.
     *
     * @return JsonResponse JSON response with a success message.
     */
    public function destroy( string $slug ): JsonResponse
    {
        try {
            $this->pluginManager->delete( $slug );
        } catch ( PluginNotFoundException $e ) {
            throw ValidationException::withMessages( [
                'slug' => $e->getMessage(),
            ] );
        } catch ( Exception $e ) {
            report( $e );

            throw ValidationException::withMessages( [
                'slug' => __( 'An unexpected error occurred while deleting the plugin.' ),
            ] );
        }

        return response()->json( [
            'message' => __( 'Plugin deleted successfully' ),
        ] );
    }

    /**
     * GET /api/v1/plugins/updates
     * Check for available updates.
     */
    public function checkUpdates(): JsonResponse
    {
        $updates = $this->updateManager->checkForUpdates();

        return response()->json( [
            'updates' => $updates,
        ] );
    }

    /**
     * Updates an installed plugin to its latest published version.
     *
     * Endpoint: POST /api/v1/plugins/{slug}/update
     *
     * @since 1.0.0
     *
     * @param  string  $slug  Plugin slug identifier.
     *
     * @throws ValidationException If the update fails.
     *
     * @return JsonResponse JSON response reporting whether an update was installed.
     */
    public function update( string $slug ): JsonResponse
    {
        try {
            $updated = $this->updateManager->updatePlugin( $slug );
        } catch ( PluginUpdateException $e ) {
            // `UpdateManager` funnels every in-flight failure — unknown slug,
            // download, checksum mismatch, failed swap — through this type,
            // carrying the underlying reason verbatim.
            throw ValidationException::withMessages( [
                'slug' => $e->getMessage(),
            ] );
        } catch ( Exception $e ) {
            report( $e );

            throw ValidationException::withMessages( [
                'slug' => __( 'An unexpected error occurred while updating the plugin.' ),
            ] );
        }

        return response()->json( [
            'message' => $updated
                ? __( 'Plugin updated successfully' )
                : __( 'Plugin is already up to date.' ),
            'updated' => $updated,
        ] );
    }

    /**
     * Reports a plugin's declared dependencies and their current status.
     *
     * Endpoint: GET /api/v1/plugins/{slug}/dependencies
     *
     * @since 2.9.0
     *
     * @param  string  $slug  Plugin slug identifier.
     *
     * @return JsonResponse JSON response with the declared requires/conflicts and resolved status.
     */
    public function dependencies( string $slug ): JsonResponse
    {
        $plugin = $this->pluginManager->getPlugin( $slug );

        if ( ! $plugin ) {
            return response()->json( [
                'message' => __( 'Plugin not found' ),
            ], 404 );
        }

        return response()->json( [
            'slug'      => $slug,
            'requires'  => $plugin['manifest']['requires'] ?? [],
            'conflicts' => $plugin['manifest']['conflicts'] ?? [],
            'status'    => $this->pluginManager->checkDependencies( $slug )->toArray(),
        ] );
    }

    /**
     * Reports the installed plugins that depend on the given plugin.
     *
     * Endpoint: GET /api/v1/plugins/{slug}/dependents
     *
     * @since 2.9.0
     *
     * @param  string  $slug  Plugin slug identifier.
     *
     * @return JsonResponse JSON response listing dependent plugin slugs.
     */
    public function dependents( string $slug ): JsonResponse
    {
        $graph = $this->pluginManager->buildDependencyGraph();

        return response()->json( [
            'slug'           => $slug,
            'dependents'     => $this->pluginManager->getDependents( $slug, $graph ),
            'can_deactivate' => $this->pluginManager->canDeactivate( $slug, $graph ),
        ] );
    }

    /**
     * Checks dependencies for a batch of plugins and returns a suggested
     * activation order.
     *
     * Accepts either `{"plugins": ["a", "b"]}` or a bare JSON array body.
     *
     * Endpoint: POST /api/v1/plugins/check-dependencies
     *
     * @since 2.9.0
     *
     * @param  Request  $request  Request carrying the plugin slugs to check.
     *
     * @return JsonResponse JSON response with per-plugin status and activation order.
     */
    public function checkDependencies( Request $request ): JsonResponse
    {
        $slugs = $request->input( 'plugins' );
        if ( ! is_array( $slugs ) ) {
            $all   = $request->all();
            $slugs = array_is_list( $all ) ? $all : [];
        }
        $slugs = array_values( array_filter( $slugs, 'is_string' ) );

        $graph   = $this->pluginManager->buildDependencyGraph();
        $results = [];
        foreach ( $slugs as $slug ) {
            $results[ $slug ] = $this->pluginManager->checkDependencies( $slug, $graph )->toArray();
        }

        try {
            $order = $this->pluginManager->getActivationOrder( $slugs, $graph );
        } catch ( CircularDependencyException $e ) {
            return response()->json( [
                'message' => $e->getMessage(),
                'code'    => 'circular_dependency',
                'cycle'   => $e->cycle,
                'results' => $results,
            ], 422 );
        }

        return response()->json( [
            'order'   => $order,
            'results' => $results,
        ] );
    }
}
