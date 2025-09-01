<?php

use ArtisanPackUI\CMSFramework\Features\Plugins\PluginManager;
use ArtisanPackUI\CMSFramework\Http\Controllers\ContentController;
use ArtisanPackUI\CMSFramework\Http\Controllers\ContentTypeController;
use ArtisanPackUI\CMSFramework\Http\Controllers\MediaCategoryController;
use ArtisanPackUI\CMSFramework\Http\Controllers\MediaController;
use ArtisanPackUI\CMSFramework\Http\Controllers\MediaTagController;
use ArtisanPackUI\CMSFramework\Http\Controllers\RoleController;
use ArtisanPackUI\CMSFramework\Http\Controllers\SettingController;
use ArtisanPackUI\CMSFramework\Http\Controllers\TaxonomyController;
use ArtisanPackUI\CMSFramework\Http\Controllers\TermController;
use ArtisanPackUI\CMSFramework\Http\Controllers\UserController;
use ArtisanPackUI\CMSFramework\Http\Controllers\SearchController;
use ArtisanPackUI\CMSFramework\Models\Plugin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// General API endpoints with standard rate limiting (60 req/min)
Route::middleware(['api', 'cms.rate_limit.general'])->prefix('api/cms')->group(function () {
    // Content and media CRUD operations
    Route::apiResource('content', ContentController::class);
    Route::apiResource('media', MediaController::class)->parameters([
        'media' => 'media',
    ]);
    Route::apiResource('media-categories', MediaCategoryController::class)->parameters([
        'media-categories' => 'media_category',
    ]);
    Route::apiResource('media-tags', MediaTagController::class)->parameters([
        'media-tags' => 'media_tag',
    ]);
    Route::apiResource('taxonomies', TaxonomyController::class);
    Route::apiResource('terms', TermController::class);

    // List all installed plugins (read-only operation)
    Route::get('plugins', function () {
        return response()->json(Plugin::all());
    });

    // Search endpoints
    Route::prefix('search')->controller(SearchController::class)->group(function () {
        Route::get('/', 'search')->name('search');
        Route::get('/facets', 'facets')->name('search.facets');
        Route::get('/suggestions', 'suggestions')->name('search.suggestions');
        Route::get('/status', 'status')->name('search.status');
    });
});

// Administrative endpoints with admin rate limiting (30 req/min)
Route::middleware(['api', 'cms.rate_limit.admin'])->prefix('api/cms')->group(function () {
    // User and role management
    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RoleController::class);

    // Settings management
    Route::apiResource('settings', SettingController::class);

    // Content type management
    Route::apiResource('content-types', ContentTypeController::class);

    // Search analytics (admin only)
    Route::get('search/analytics', [SearchController::class, 'analytics'])->name('search.analytics');

    // Plugin management (non-upload operations)
    Route::post('plugins/install-from-url', function (Request $request, PluginManager $pluginManager) {
        $request->validate([
            'plugin_url' => 'required|url',
        ]);

        try {
            $plugin = $pluginManager->installFromUrl(sanitizeUrl($request->input('plugin_url')));

            return response()->json([
                'message' => 'Plugin installed successfully from URL!',
                'plugin' => $plugin,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Plugin installation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    });

    // Activate a plugin by its slug
    Route::post('plugins/{name}/activate', function (string $name, PluginManager $pluginManager) {
        try {
            $plugin = $pluginManager->activatePlugin($name);

            return response()->json([
                'message' => 'Plugin activated successfully!',
                'plugin' => $plugin,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to activate plugin',
                'error' => $e->getMessage(),
            ], 500);
        }
    });

    // Deactivate a plugin by its slug
    Route::post('plugins/{name}/deactivate', function (string $name, PluginManager $pluginManager) {
        try {
            $plugin = $pluginManager->deactivatePlugin($name);

            return response()->json([
                'message' => 'Plugin deactivated successfully!',
                'plugin' => $plugin,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to deactivate plugin',
                'error' => $e->getMessage(),
            ], 500);
        }
    });

    // Uninstall a plugin by its slug
    Route::delete('plugins/{name}', function (string $name, PluginManager $pluginManager) {
        try {
            $pluginManager->uninstallPlugin($name);

            return response()->json(['message' => 'Plugin uninstalled successfully!']);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to uninstall plugin',
                'error' => $e->getMessage(),
            ], 500);
        }
    });
});

// Upload endpoints with restrictive upload rate limiting (10 req/min)
Route::middleware(['api', 'cms.rate_limit.upload'])->prefix('api/cms')->group(function () {
    // Upload and install a plugin from zip
    Route::post('plugins/upload', function (Request $request, PluginManager $pluginManager) {
        $request->validate([
            'plugin_zip' => 'required|file|mimes:zip|max:102400', // Max 100MB
        ]);

        try {
            $path = $request->file('plugin_zip')->store('temp_plugin_uploads');
            $fullPath = storage_path('app/'.$path);
            $plugin = $pluginManager->installFromZip($fullPath);

            return response()->json([
                'message' => 'Plugin installed successfully!',
                'plugin' => $plugin,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Plugin installation failed',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            if (isset($fullPath) && file_exists($fullPath)) {
                unlink($fullPath); // Clean up temp file
            }
        }
    });

    // Update a plugin from zip by its slug
    Route::post('plugins/{name}/update', function (string $name, Request $request, PluginManager $pluginManager) {
        $request->validate([
            'plugin_zip' => 'required|file|mimes:zip|max:102400', // Max 100MB
        ]);

        try {
            $path = $request->file('plugin_zip')->store('temp_plugin_updates');
            $fullPath = storage_path('app/'.$path);
            $plugin = $pluginManager->updateFromZip($fullPath, $name);

            return response()->json([
                'message' => 'Plugin updated successfully!',
                'plugin' => $plugin,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Plugin update failed',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            if (isset($fullPath) && file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    });
});
