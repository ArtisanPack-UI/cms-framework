<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Managers;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginUpdateException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class UpdateManager
{
    public function __construct(
        private PluginManager $pluginManager
    ) {}

    /**
     * Check for updates for all active plugins.
     *
     * @return array Array of plugins with available updates
     */
    public function checkForUpdates(): array
    {
        $plugins = Plugin::all();
        $updates = [];

        foreach ($plugins as $plugin) {
            $updateInfo = $this->checkPluginUpdate($plugin->slug);

            if ($updateInfo) {
                $updates[$plugin->slug] = $updateInfo;
            }
        }

        return $updates;
    }

    /**
     * Check update for specific plugin.
     *
     * @param  string  $slug  Plugin slug
     * @return array|null Update info or null if no update available
     */
    public function checkPluginUpdate(string $slug): ?array
    {
        $plugin = Plugin::where('slug', $slug)->first();

        if (! $plugin || ! isset($plugin->meta['update_url'])) {
            return null;
        }

        $cacheKey = "plugin.update.{$slug}";

        return Cache::remember($cacheKey, config('cms.plugins.updateCacheTtl'), function () use ($plugin) {
            try {
                $response = Http::timeout(config('cms.plugins.updateCheckTimeout'))
                    ->get($plugin->meta['update_url']);

                if (! $response->successful()) {
                    return null;
                }

                $updateData = $response->json();

                if ($this->isUpdateAvailable($plugin->version, $updateData['version'] ?? '')) {
                    return $updateData;
                }
            } catch (\Exception $e) {
                logger()->error("Failed to check update for plugin: {$plugin->slug}", [
                    'exception' => $e->getMessage(),
                ]);
            }

            return null;
        });
    }

    /**
     * Update a plugin to latest version.
     *
     * @param  string  $slug  Plugin slug
     * @return bool True on success
     *
     * @throws PluginUpdateException On update failure
     */
    public function updatePlugin(string $slug): bool
    {
        $plugin = Plugin::where('slug', $slug)->first();

        if (! $plugin) {
            throw PluginUpdateException::downloadFailed($slug);
        }

        $updateInfo = $this->checkPluginUpdate($slug);

        if (! $updateInfo) {
            return false; // No update available
        }

        $wasActive = $plugin->is_active;
        $oldVersion = $plugin->version;

        doAction('plugin.updating', $slug, $oldVersion, $updateInfo['version']);

        try {
            // 1. Backup current version
            $backupPath = $this->backupPlugin($slug);

            // 2. Deactivate if active
            if ($wasActive) {
                $this->pluginManager->deactivate($slug);
            }

            // 3. Download new version
            $zipPath = $this->downloadUpdate($updateInfo['download_url']);

            // 4. Delete old files
            $pluginPath = base_path(config('cms.plugins.directory').'/'.$slug);
            File::deleteDirectory($pluginPath);

            // 5. Extract new version
            $zip = new \ZipArchive;
            $zip->open($zipPath);
            $zip->extractTo(base_path(config('cms.plugins.directory')));
            $zip->close();

            // 6. Update database
            $manifestPath = $pluginPath.'/plugin.json';
            $manifest = json_decode(File::get($manifestPath), true);

            $plugin->version = $updateInfo['version'];
            $plugin->meta = $manifest;
            $plugin->service_provider = $manifest['service_provider'] ?? null;
            $plugin->save();

            // 7. Reactivate if was active
            if ($wasActive) {
                $this->pluginManager->activate($slug);
            }

            // 8. Cleanup
            File::delete($zipPath);

            doAction('plugin.updated', $slug, $updateInfo['version']);

            return true;
        } catch (\Exception $e) {
            // Restore from backup on failure
            $this->restoreFromBackup($slug, $backupPath);

            throw PluginUpdateException::downloadFailed($slug);
        }
    }

    /**
     * Backup plugin before update.
     *
     * @param  string  $slug  Plugin slug
     * @return string Backup path
     */
    protected function backupPlugin(string $slug): string
    {
        $plugin = Plugin::where('slug', $slug)->first();
        $pluginPath = base_path(config('cms.plugins.directory').'/'.$slug);

        $backupDir = storage_path(config('cms.plugins.backupPath'));
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $backupFile = $backupDir.'/'.$slug.'-'.$plugin->version.'-'.time().'.zip';

        $zip = new \ZipArchive;
        $zip->open($backupFile, \ZipArchive::CREATE);

        $files = File::allFiles($pluginPath);
        foreach ($files as $file) {
            $relativePath = str_replace($pluginPath.'/', '', $file->getPathname());
            $zip->addFile($file->getPathname(), $relativePath);
        }

        $zip->close();

        return $backupFile;
    }

    /**
     * Restore plugin from backup on failure.
     *
     * @param  string  $slug  Plugin slug
     * @param  string  $backupPath  Path to backup
     */
    protected function restoreFromBackup(string $slug, string $backupPath): void
    {
        $pluginPath = base_path(config('cms.plugins.directory').'/'.$slug);

        // Delete failed update
        if (File::exists($pluginPath)) {
            File::deleteDirectory($pluginPath);
        }

        // Extract backup
        $zip = new \ZipArchive;
        $zip->open($backupPath);
        $zip->extractTo($pluginPath);
        $zip->close();
    }

    /**
     * Download plugin update from URL.
     *
     * @param  string  $updateUrl  URL to download ZIP
     * @return string Path to downloaded file
     */
    protected function downloadUpdate(string $updateUrl): string
    {
        $response = Http::timeout(60)->get($updateUrl);

        if (! $response->successful()) {
            throw new \Exception('Failed to download update');
        }

        $tempPath = storage_path('app/temp-plugin-'.time().'.zip');
        File::put($tempPath, $response->body());

        return $tempPath;
    }

    /**
     * Compare version numbers.
     *
     * @param  string  $current  Current version
     * @param  string  $available  Available version
     * @return bool True if update available
     */
    protected function isUpdateAvailable(string $current, string $available): bool
    {
        return version_compare($available, $current, '>');
    }
}
