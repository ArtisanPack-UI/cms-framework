<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\UpdateManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PluginsController extends Controller
{
    public function __construct(
        private PluginManager $pluginManager,
        private UpdateManager $updateManager,
    ) {}

    /**
     * GET /api/v1/plugins
     * List all discovered plugins.
     */
    public function index(): JsonResponse
    {
        $plugins = $this->pluginManager->discoverPlugins();

        return response()->json([
            'plugins' => $plugins,
        ]);
    }

    /**
     * GET /api/v1/plugins/{slug}
     * Get specific plugin details.
     */
    public function show(string $slug): JsonResponse
    {
        $plugin = $this->pluginManager->getPlugin($slug);

        if (! $plugin) {
            return response()->json([
                'message' => 'Plugin not found',
            ], 404);
        }

        return response()->json([
            'plugin' => $plugin,
        ]);
    }

    /**
     * POST /api/v1/plugins/install
     * Upload and install plugin ZIP.
     */
    public function install(Request $request): JsonResponse
    {
        $request->validate([
            'plugin_zip' => 'required|file|mimes:zip|max:'.(config('cms.plugins.maxUploadSize') / 1024),
        ]);

        try {
            $zipPath = $request->file('plugin_zip')->path();
            $plugin = $this->pluginManager->installFromZip($zipPath);

            return response()->json([
                'message' => 'Plugin installed successfully',
                'plugin' => $plugin,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Installation failed: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/plugins/{slug}/activate
     * Activate a plugin.
     */
    public function activate(string $slug): JsonResponse
    {
        try {
            $this->pluginManager->activate($slug);

            return response()->json([
                'message' => 'Plugin activated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Activation failed: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/plugins/{slug}/deactivate
     * Deactivate a plugin.
     */
    public function deactivate(string $slug): JsonResponse
    {
        try {
            $this->pluginManager->deactivate($slug);

            return response()->json([
                'message' => 'Plugin deactivated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Deactivation failed: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * DELETE /api/v1/plugins/{slug}
     * Delete a plugin.
     */
    public function destroy(string $slug): JsonResponse
    {
        try {
            $this->pluginManager->delete($slug);

            return response()->json([
                'message' => 'Plugin deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Deletion failed: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/v1/plugins/updates
     * Check for available updates.
     */
    public function checkUpdates(): JsonResponse
    {
        $updates = $this->updateManager->checkForUpdates();

        return response()->json([
            'updates' => $updates,
        ]);
    }

    /**
     * POST /api/v1/plugins/{slug}/update
     * Update a plugin to latest version.
     */
    public function update(string $slug): JsonResponse
    {
        try {
            $this->updateManager->updatePlugin($slug);

            return response()->json([
                'message' => 'Plugin updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Update failed: '.$e->getMessage(),
            ], 422);
        }
    }
}
