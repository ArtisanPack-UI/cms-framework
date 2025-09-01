<?php

namespace ArtisanPackUI\CMSFramework\Features\Plugins;

// phpcs:disable
use ArtisanPackUI\CMSFramework\Contracts\PluginManagerInterface;
use ArtisanPackUI\CMSFramework\Contracts\SettingsManagerInterface;
use ArtisanPackUI\CMSFramework\Features\Plugins\Plugin as BasePlugin;
use ArtisanPackUI\CMSFramework\Models\Plugin;
use ArtisanPackUI\CMSFramework\Services\CacheService;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class PluginManager implements PluginManagerInterface
{
    protected string $pluginPath;

    protected string $appComposerPath;

    protected array $loadedPluginInstances = [];

    protected CacheService $cacheService;

    public function __construct(?CacheService $cacheService = null)
    {
        $this->pluginPath = config('cms.paths.plugins', base_path('plugins'));
        $this->appComposerPath = base_path('composer.json');
        $this->cacheService = $cacheService ?? app(CacheService::class);

        // Ensure the plugin storage directory exists
        if (! File::exists($this->pluginPath)) {
            File::makeDirectory($this->pluginPath, 0755, true, true);
        }
    }

    /**
     * Initializes active plugins by calling their register() and boot() methods.
     * This is called by your main Framework Service Provider during application boot.
     */
    public function initializeActivePlugins(): void
    {
        // Retrieve all currently active plugins from the database with caching
        $activePlugins = $this->cacheService->remember(
            'plugins',
            'active_plugins',
            function () {
                return Plugin::where('is_active', true)->get(); // Use renamed Model class
            }
        );

        // First pass: Instantiate all active plugins and cache their instances
        foreach ($activePlugins as $pluginModel) {
            try {
                $pluginInstance = $pluginModel->instance;
                $this->loadedPluginInstances[$pluginInstance->slug] = $pluginInstance;
            } catch (RuntimeException $e) {
                Log::error("Failed to instantiate plugin '{$pluginModel->slug}' during initialization. This plugin might be broken or its files are missing. Error: ".$e->getMessage());
                // Consider automatically deactivating such a plugin or alerting the admin
            }
        }

        // Second pass: Call the register() method for all loaded active plugin instances.
        // This is where plugins register service container bindings, settings, etc.
        foreach ($this->loadedPluginInstances as $pluginInstance) {
            try {
                $pluginInstance->register();
                // Automatically register plugin's settings using the framework's SettingsManager
                foreach ($pluginInstance->registerSettings() as $setting) {
                    app(SettingsManagerInterface::class)->register( // Use interface
                        $setting['key'],
                        $setting['default'] ?? null,
                        $setting['type'] ?? null,
                        $setting['description'] ?? null
                    );
                }
            } catch (Throwable $e) {
                Log::error("Error during register() phase for plugin '{$pluginInstance->slug}': ".$e->getMessage());
            }
        }

        // Third pass: Call the boot() method for all loaded active plugin instances.
        // This is where plugins typically register Eventy hooks, routes, views, etc.
        foreach ($this->loadedPluginInstances as $pluginInstance) {
            try {
                $pluginInstance->boot();
            } catch (Throwable $e) {
                Log::error("Error during boot() phase for plugin '{$pluginInstance->slug}': ".$e->getMessage());
            }
        }
    }

    /**
     * Gets all installed plugin models.
     *
     * @return Collection<Plugin>
     */
    public function getAllInstalled(): Collection
    {
        return $this->cacheService->remember(
            'plugins',
            'all_installed',
            function () {
                return Plugin::all(); // Use renamed Model class
            }
        );
    }

    /**
     * Gets an active plugin instance by its slug.
     */
    public function getActiveInstance(string $slug): ?BasePlugin
    {
        return $this->loadedPluginInstances[$slug] ?? null;
    }

    /**
     * Installs a plugin by downloading a zip file from a URL.
     *
     * @param  string  $url  The URL of the zip file.
     * @return Plugin The newly installed plugin model.
     *
     * @throws Exception
     */
    public function installFromUrl(string $url): Plugin // Return renamed Model class
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid URL provided: {$url}");
        }

        $tempFileName = uniqid('plugin_download_').'.zip';
        $tempFilePath = storage_path('app/temp_plugin_downloads/'.$tempFileName);
        File::makeDirectory(dirname($tempFilePath), 0755, true, true);

        try {
            $response = Http::sink($tempFilePath)->get($url);

            if (! $response->successful()) {
                throw new Exception("Failed to download zip from URL: {$url}. Status: ".$response->status());
            }

            $plugin = $this->installFromZip($tempFilePath); // Use the internal zip installation method

            return $plugin;

        } catch (Exception $e) {
            throw $e; // Re-throw for upstream handling
        } finally {
            // Ensure the temporary download file is always deleted
            if (File::exists($tempFilePath)) {
                File::delete($tempFilePath);
            }
        }
    }

    /**
     * Installs a plugin from a local zip file path.
     * This method can be used for direct uploads or by installFromUrl.
     *
     * @param  string  $zipFilePath  The absolute path to the zip file.
     * @return Plugin The newly installed plugin model.
     *
     * @throws Exception
     */
    public function installFromZip(string $zipFilePath): Plugin // Return renamed Model class
    {
        $zip = new ZipArchive;
        if ($zip->open($zipFilePath) !== true) {
            throw new Exception("Could not open zip file: {$zipFilePath}");
        }

        $tempExtractDir = storage_path('app/temp_plugin_extracts/'.uniqid('plugin_extract_'));
        File::makeDirectory($tempExtractDir, 0755, true, true);

        $zip->extractTo($tempExtractDir);
        $zip->close();

        // Find the actual plugin root directory (it's typically a single directory inside the zip)
        $extractedContents = File::directories($tempExtractDir);
        if (count($extractedContents) !== 1) {
            File::deleteDirectory($tempExtractDir);
            throw new Exception('Invalid plugin zip file structure. Expected a single root directory inside the zip.');
        }
        $pluginSourceDir = $extractedContents[0];
        $pluginDirectoryName = basename($pluginSourceDir);

        $destinationPath = $this->pluginPath.'/'.$pluginDirectoryName;

        // Check if a plugin directory with this name already exists at the final destination
        if (File::exists($destinationPath)) {
            File::deleteDirectory($tempExtractDir);
            throw new Exception("Plugin directory '{$pluginDirectoryName}' already exists. Consider updating instead.");
        }

        // 1. Validate Composer.json to get package name and PSR-4 mappings
        $composerContent = $this->validateComposerJson($pluginSourceDir);
        $composerPackageName = $composerContent['name'];

        // 2. Discover the FQCN of the plugin's main Plugin.php class
        $pluginMainClass = $this->discoverPluginClass($pluginSourceDir, $composerContent);

        // 3. Temporarily add to app's composer.json and dump-autoload to make plugin class discoverable
        // This makes `app($pluginMainClass)` possible immediately after.
        $this->addPluginToAppComposer($composerPackageName, $pluginSourceDir);
        $this->runComposerDumpAutoload();

        // 4. Instantiate plugin class to get its metadata from its properties (slug, version, etc.)
        try {
            /** @var BasePlugin $pluginInstance */
            $pluginInstance = app($pluginMainClass); // Resolve from container to get dependencies
        } catch (Throwable $e) {
            // Rollback Composer.json changes if Plugin class fails to load/instantiate
            $this->removePluginFromAppComposer($composerPackageName, $pluginSourceDir);
            $this->runComposerDumpAutoload();
            File::deleteDirectory($tempExtractDir);
            throw new Exception("Failed to load or instantiate plugin class '{$pluginMainClass}'. Error: ".$e->getMessage(), 0, $e);
        }

        // 5. Check for conflicts (duplicate slug or composer package name)
        if (Plugin::where('slug', $pluginInstance->slug)->exists() || Plugin::where('composer_package_name', $composerPackageName)->exists()) { // Use renamed Model class
            $this->removePluginFromAppComposer($composerPackageName, $pluginSourceDir); // Rollback Composer.json
            $this->runComposerDumpAutoload();
            File::deleteDirectory($tempExtractDir);
            throw new Exception("Plugin with slug '{$pluginInstance->slug}' or Composer package '{$composerPackageName}' is already registered.");
        }

        // 6. Move the extracted plugin to its final destination
        File::moveDirectory($pluginSourceDir, $destinationPath, true);
        File::deleteDirectory($tempExtractDir); // Clean up temp directory

        // 7. Re-add plugin to app's composer.json with its *final* path (if different from source)
        // (Often, source path is already the final path, so this step might be redundant if previous was sufficient)
        $this->addPluginToAppComposer($composerPackageName, $destinationPath); // Ensure it points to the final path
        $this->runComposerDumpAutoload(); // Final dump-autoload after file move
        $this->clearCaches(); // Clear Laravel caches

        // 8. Record the plugin in the database as inactive initially
        $plugin = Plugin::create([ // Use renamed Model class
            'slug' => $pluginInstance->slug,
            'composer_package_name' => $composerPackageName,
            'directory_name' => $pluginDirectoryName,
            'plugin_class' => $pluginMainClass,
            'version' => $pluginInstance->version, // Use version from Plugin.php
            'is_active' => false, // Set to false initially, activate separately
            'config' => [], // Initialize with empty config
        ]);

        return $plugin;
    }

    /**
     * Validates a plugin's composer.json structure.
     *
     * @param  string  $pluginDirectoryPath  The absolute path to the plugin's directory.
     * @return array The decoded composer.json content.
     *
     * @throws Exception If validation fails.
     */
    protected function validateComposerJson(string $pluginDirectoryPath): array
    {
        $composerFilePath = $pluginDirectoryPath.'/composer.json';
        if (! File::exists($composerFilePath)) {
            throw new Exception("Plugin '{$pluginDirectoryPath}' does not contain a composer.json file. It is not a valid Composer package.");
        }

        $composerContent = json_decode(File::get($composerFilePath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in plugin's composer.json: ".json_last_error_msg());
        }

        if (! isset($composerContent['name']) || empty($composerContent['name'])) {
            throw new Exception("Plugin's composer.json is missing the 'name' field.");
        }

        if (! isset($composerContent['autoload']['psr-4']) || empty($composerContent['autoload']['psr-4'])) {
            throw new Exception("Plugin's composer.json is missing 'autoload.psr-4' definition, required for class discovery.");
        }

        return $composerContent;
    }

    /**
     * Discovers the main Plugin.php class within an extracted plugin directory.
     * Relies on 'autoload.psr-4' in composer.json to find the primary namespace.
     *
     * @param  string  $pluginSourceDir  The absolute path to the plugin's extracted source directory.
     * @param  array  $composerContent  The decoded composer.json content of the plugin.
     * @return string Fully qualified class name of the main Plugin class.
     *
     * @throws Exception If the plugin class cannot be found or does not extend BasePlugin.
     */
    protected function discoverPluginClass(string $pluginSourceDir, array $composerContent): string
    {
        if (! isset($composerContent['autoload']['psr-4']) || empty($composerContent['autoload']['psr-4'])) {
            throw new Exception("Plugin's composer.json must define 'autoload.psr-4' for class discovery.");
        }

        // Get the first defined PSR-4 mapping (assumes this is the main namespace for the Plugin.php class)
        $mainNamespace = '';
        $mainPath = '';
        foreach ($composerContent['autoload']['psr-4'] as $namespace => $path) {
            $mainNamespace = $namespace;
            $mainPath = $path;
            break;
        }

        // Construct potential Plugin class FQCN (e.g., ArtisanpackPlugins\Seo\Plugin)
        $pluginClassFqcn = rtrim($mainNamespace, '\\').'\\Plugin';

        // Validate that the Plugin.php file exists at the expected path
        $classFile = $pluginSourceDir.'/'.ltrim($mainPath, './').'Plugin.php'; // Handle paths like "./src" or "src"
        if (! File::exists($classFile)) {
            throw new Exception("Could not find Plugin.php class file at expected path: '{$classFile}'. Ensure your main plugin class is named 'Plugin.php' and is in the root of your primary PSR-4 namespace.");
        }

        // Verify that the class exists and is a valid subclass of your framework's BasePlugin.
        // This relies on runComposerDumpAutoload() having been called recently.
        if (! class_exists($pluginClassFqcn)) {
            throw new Exception("Plugin class '{$pluginClassFqcn}' does not exist or is not autoloadable. Ensure Composer dump-autoload completed successfully.");
        }

        try {
            $reflection = new ReflectionClass($pluginClassFqcn);
            if (! $reflection->isSubclassOf(BasePlugin::class) || $reflection->isAbstract()) {
                throw new Exception("Plugin class '{$pluginClassFqcn}' must extend ".BasePlugin::class.' and not be abstract.');
            }
        } catch (ReflectionException $e) {
            throw new Exception("Error reflecting plugin class '{$pluginClassFqcn}': ".$e->getMessage(), 0, $e);
        }

        return $pluginClassFqcn;
    }

    /**
     * Adds a plugin's path repository and requirement to the main application's composer.json.
     *
     * @param  string  $packageName  The Composer package name.
     * @param  string  $pluginPath  The absolute path to the plugin directory (source or destination).
     *
     * @throws Exception
     */
    protected function addPluginToAppComposer(string $packageName, string $pluginPath): void
    {
        if (! File::exists($this->appComposerPath)) {
            throw new Exception("Application's composer.json not found at: {$this->appComposerPath}");
        }

        $appComposerContent = json_decode(File::get($this->appComposerPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in application's composer.json: ".json_last_error_msg());
        }

        // Initialize 'repositories' if not present
        if (! isset($appComposerContent['repositories'])) {
            $appComposerContent['repositories'] = [];
        }

        // Add 'path' repository if not already present
        $repoExists = false;
        foreach ($appComposerContent['repositories'] as $repo) {
            if (isset($repo['type']) && $repo['type'] === 'path' && isset($repo['url']) && $repo['url'] === $pluginPath) {
                $repoExists = true;
                break;
            }
        }
        if (! $repoExists) {
            $appComposerContent['repositories'][] = [
                'type' => 'path',
                'url' => $pluginPath,
                'options' => [
                    'symlink' => false, // Set to true if you prefer symlinks (dev environments)
                ],
            ];
        }

        // Add the plugin to the 'require' section
        $appComposerContent['require'][$packageName] = '*'; // Require any version from the path

        File::put($this->appComposerPath, json_encode($appComposerContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Executes 'composer dump-autoload' command.
     * In a production environment, this should ideally be dispatched to a queue.
     *
     * @throws ProcessFailedException
     */
    protected function runComposerDumpAutoload(): void
    {
        $composerBinary = 'composer'; // Adjust if Composer is not in PATH
        // More robust composer path detection logic could go here

        $command = [$composerBinary, 'dump-autoload', '--optimize']; // --optimize for production performance

        $process = new Process($command, base_path());
        $process->setTimeout(300); // 5 minutes timeout for Composer operations

        try {
            $process->run();
            if (! $process->isSuccessful()) {
                Log::error('Composer dump-autoload failed: '.$process->getErrorOutput());
                throw new ProcessFailedException($process);
            }
            Log::info('Composer dump-autoload successful.');
        } catch (ProcessFailedException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new Exception('Unexpected error during composer dump-autoload: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Removes a plugin's path repository and requirement from the main application's composer.json.
     *
     * @param  string  $packageName  The Composer package name.
     * @param  string  $pluginPath  The absolute path to the plugin directory (must match url in composer.json).
     *
     * @throws Exception
     */
    public function removePluginFromAppComposer(string $packageName, string $pluginPath): void
    {
        if (! File::exists($this->appComposerPath)) {
            throw new Exception("Application's composer.json not found at: {$this->appComposerPath}");
        }

        $appComposerContent = json_decode(File::get($this->appComposerPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in application's composer.json: ".json_last_error_msg());
        }

        // Remove from 'require' section
        if (isset($appComposerContent['require'][$packageName])) {
            unset($appComposerContent['require'][$packageName]);
        }

        // Remove 'path' repository by matching URL
        if (isset($appComposerContent['repositories'])) {
            $appComposerContent['repositories'] = array_values(array_filter($appComposerContent['repositories'], function ($repo) use ($pluginPath) {
                return ! (isset($repo['type']) && $repo['type'] === 'path' && isset($repo['url']) && $repo['url'] === $pluginPath);
            }));
        }

        File::put($this->appComposerPath, json_encode($appComposerContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Clears various Laravel caches.
     */
    protected function clearCaches(): void
    {
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('config:clear');
        Log::info('Laravel caches cleared.');
    }

    /**
     * Updates an installed plugin from a new zip file.
     *
     * @param  string  $zipFilePath  The absolute path to the new zip file.
     * @param  string  $pluginSlug  The framework-specific slug of the plugin to update.
     * @return Plugin The updated plugin model.
     *
     * @throws Exception
     */
    public function updateFromZip(string $zipFilePath, string $pluginSlug): Plugin // Return renamed Model class
    {
        $existingPluginModel = Plugin::where('slug', $pluginSlug)->firstOrFail(); // Use renamed Model class
        $existingPluginPath = $this->pluginPath.'/'.$existingPluginModel->directory_name;

        $zip = new ZipArchive;
        if ($zip->open($zipFilePath) !== true) {
            throw new Exception("Could not open update zip file: {$zipFilePath}");
        }

        $tempUpdateDir = storage_path('app/temp_plugin_updates/'.uniqid('plugin_update_'));
        File::makeDirectory($tempUpdateDir, 0755, true, true);

        $zip->extractTo($tempUpdateDir);
        $zip->close();

        $extractedContents = File::directories($tempUpdateDir);
        if (count($extractedContents) !== 1) {
            File::deleteDirectory($tempUpdateDir);
            throw new Exception('Invalid plugin zip file structure for update.');
        }
        $newPluginSourceDir = $extractedContents[0];
        $newPluginDirectoryName = basename($newPluginSourceDir);

        // Validate Composer.json and discover Plugin.php class from the new version
        $newComposerContent = $this->validateComposerJson($newPluginSourceDir);
        $newPluginMainClass = $this->discoverPluginClass($newPluginSourceDir, $newComposerContent);
        $newComposerPackageName = $newComposerContent['name'];

        // Temporarily update app's composer.json to load the new plugin class
        $this->removePluginFromAppComposer($existingPluginModel->composer_package_name, $existingPluginPath); // Remove old reference
        $this->addPluginToAppComposer($newComposerPackageName, $newPluginSourceDir); // Add new temporary reference
        $this->runComposerDumpAutoload();

        // Instantiate new plugin class to get its metadata
        try {
            /** @var BasePlugin $newPluginInstance */
            $newPluginInstance = app($newPluginMainClass);
        } catch (Throwable $e) {
            // If new plugin class fails to load, try to revert Composer.json and throw
            $this->removePluginFromAppComposer($newComposerPackageName, $newPluginSourceDir);
            $this->addPluginToAppComposer($existingPluginModel->composer_package_name, $existingPluginPath); // Restore old reference
            $this->runComposerDumpAutoload();
            File::deleteDirectory($tempUpdateDir);
            throw new Exception('Failed to load new plugin class for update: '.$e->getMessage(), 0, $e);
        }

        // Basic validation: ensure slug and composer name match or handle changes
        if ($newPluginInstance->slug !== $existingPluginModel->slug) {
            File::deleteDirectory($tempUpdateDir);
            throw new Exception("Plugin slug mismatch. Cannot update '{$existingPluginModel->slug}' with a plugin of slug '{$newPluginInstance->slug}'.");
        }
        if ($newComposerPackageName !== $existingPluginModel->composer_package_name) {
            File::deleteDirectory($tempUpdateDir);
            throw new Exception("Composer package name mismatch. Cannot update '{$existingPluginModel->composer_package_name}' with '{$newComposerPackageName}'.");
        }

        // Optional: Add version comparison logic here. If new version is not higher, throw error.

        // Backup existing plugin
        $backupPath = $existingPluginPath.'_backup_'.date('YmdHis');
        if (File::exists($existingPluginPath)) {
            File::moveDirectory($existingPluginPath, $backupPath);
        }

        try {
            // Move new plugin files into place
            File::moveDirectory($newPluginSourceDir, $existingPluginPath, true);
            File::deleteDirectory($tempUpdateDir);

            // Update database record with new version and potentially new directory name/class name
            $existingPluginModel->update([
                'version' => $newPluginInstance->version,
                'directory_name' => $newPluginDirectoryName,
                'plugin_class' => $newPluginMainClass,
            ]);

            // Run new migrations. Migrations are run during re-activation.
            foreach ($newPluginInstance->registerMigrations() as $migrationPath) {
                Artisan::call('migrate', [
                    '--path' => $existingPluginPath.'/'.$migrationPath, // Use the new path
                    '--force' => true,
                ]);
            }

            // If plugin was active, re-activate to ensure boot() logic is run and new services are registered
            if ($existingPluginModel->is_active) {
                $this->deactivatePlugin($pluginSlug); // Deactivate first to rollback old migrations if needed
                $this->activatePlugin($pluginSlug); // Reactivate to run new migrations and boot new code
            } else {
                $this->runComposerDumpAutoload();
                $this->clearCaches();
            }

            // Delete backup after successful update
            if (File::exists($backupPath)) {
                File::deleteDirectory($backupPath);
            }

            return $existingPluginModel;

        } catch (Exception $e) {
            // Attempt to rollback if the update failed
            if (File::exists($backupPath)) {
                if (File::exists($existingPluginPath)) {
                    File::deleteDirectory($existingPluginPath);
                }
                File::moveDirectory($backupPath, $existingPluginPath);
                $this->runComposerDumpAutoload();
                $this->clearCaches();
                Log::error("Rolled back plugin update for '{$pluginSlug}'.");
            }
            throw new Exception("Plugin update failed for '{$pluginSlug}': ".$e->getMessage(), 0, $e);
        } finally {
            if (File::exists($tempUpdateDir)) {
                File::deleteDirectory($tempUpdateDir);
            }
        }
    }

    /**
     * Deactivates an installed plugin.
     * This rolls back its migrations and prevents its Plugin.php methods from being called during boot.
     *
     * @param  string  $pluginSlug  The framework-specific slug of the plugin.
     * @return Plugin The deactivated plugin model.
     *
     * @throws Exception
     */
    public function deactivatePlugin(string $pluginSlug): Plugin // Return renamed Model class
    {
        $pluginModel = Plugin::where('slug', $pluginSlug)->firstOrFail(); // Use renamed Model class

        if (! $pluginModel->is_active) {
            throw new Exception("Plugin '{$pluginSlug}' is already inactive.");
        }

        // 1. Rollback plugin's migrations
        $this->rollbackPluginMigrations($pluginModel);

        // 2. Mark plugin as inactive in the database
        $pluginModel->update(['is_active' => false]);

        // 3. Remove from internal loaded instances cache
        unset($this->loadedPluginInstances[$pluginSlug]);

        // 4. Invalidate plugin caches specifically
        $this->cacheService->flushByTags(['plugins', 'discovery']);

        // 5. Clear caches and regenerate autoloader
        $this->runComposerDumpAutoload();
        $this->clearCaches();

        return $pluginModel;
    }

    /**
     * Rolls back migrations for a given plugin.
     *
     * @param  Plugin  $pluginModel  The plugin model.
     *
     * @throws Exception
     */
    protected function rollbackPluginMigrations(Plugin $pluginModel): void // Use renamed Model class
    {
        $pluginInstance = $pluginModel->instance;
        $pluginPath = $this->pluginPath.'/'.$pluginModel->directory_name;

        foreach ($pluginInstance->registerMigrations() as $migrationPath) {
            Artisan::call('migrate:rollback', [
                '--path' => $pluginPath.'/'.$migrationPath, // Path relative to plugin root
                '--force' => true, // Important for production environments
                '--step' => 1, // Rollback last batch for this path
            ]);
            Log::info("Rolled back migrations for plugin '{$pluginModel->slug}' from path: {$pluginPath}/{$migrationPath}");
        }
    }

    /**
     * Activates an installed plugin.
     * This makes its Plugin.php class's register() and boot() methods run and executes its migrations.
     *
     * @param  string  $pluginSlug  The framework-specific slug of the plugin.
     * @return Plugin The activated plugin model.
     *
     * @throws Exception
     */
    public function activatePlugin(string $pluginSlug): Plugin // Return renamed Model class
    {
        $pluginModel = Plugin::where('slug', $pluginSlug)->firstOrFail(); // Use renamed Model class

        if ($pluginModel->is_active) {
            throw new Exception("Plugin '{$pluginSlug}' is already active.");
        }

        $pluginInstance = $pluginModel->instance; // Get the instantiated Plugin object
        $pluginPath = $this->pluginPath.'/'.$pluginModel->directory_name;

        // 1. Run plugin's migrations
        foreach ($pluginInstance->registerMigrations() as $migrationPath) {
            Artisan::call('migrate', [
                '--path' => $pluginPath.'/'.$migrationPath, // Path relative to plugin root
                '--force' => true, // Important for production environments
            ]);
            Log::info("Ran migrations for plugin '{$pluginSlug}' from path: {$pluginPath}/{$migrationPath}");
        }

        // 2. Call register() and boot() methods of the plugin instance
        try {
            $pluginInstance->register();
            // Automatically register plugin's settings using the framework's SettingsManager
            foreach ($pluginInstance->registerSettings() as $setting) {
                app(SettingsManagerInterface::class)->register( // Use interface
                    $setting['key'],
                    $setting['default'] ?? null,
                    $setting['type'] ?? null,
                    $setting['description'] ?? null
                );
            }
            $pluginInstance->boot();
        } catch (Throwable $e) {
            // If plugin fails to boot, rollback migrations and deactivate
            Log::error("Failed to boot plugin '{$pluginSlug}' during activation. Rolling back. Error: ".$e->getMessage());
            $this->rollbackPluginMigrations($pluginModel); // Rollback migrations
            $pluginModel->update(['is_active' => false]); // Mark as inactive
            $this->runComposerDumpAutoload(); // Refresh autoloader
            $this->clearCaches();
            throw new Exception("Failed to activate plugin '{$pluginSlug}': ".$e->getMessage(), 0, $e);
        }

        // 3. Mark plugin as active in the database
        $pluginModel->update(['is_active' => true]);

        // 4. Refresh internal loaded instances cache (so it appears active for current request if needed)
        $this->loadedPluginInstances[$pluginSlug] = $pluginInstance;

        // 5. Invalidate plugin caches specifically
        $this->cacheService->flushByTags(['plugins', 'discovery']);

        // 6. Clear caches and regenerate autoloader (final step for changes to take effect)
        $this->runComposerDumpAutoload();
        $this->clearCaches();

        return $pluginModel;
    }

    /**
     * Uninstalls a plugin completely (deactivates, removes files and composer entry).
     *
     * @param  string  $pluginSlug  The framework-specific slug of the plugin.
     *
     * @throws Exception
     */
    public function uninstallPlugin(string $pluginSlug): void
    {
        $pluginModel = Plugin::where('slug', $pluginSlug)->firstOrFail(); // Use renamed Model class
        $pluginPath = $this->pluginPath.'/'.$pluginModel->directory_name;

        // 1. Deactivate if active (this will rollback migrations)
        if ($pluginModel->is_active) {
            $this->deactivatePlugin($pluginSlug);
        }

        // 2. Remove from app's composer.json
        $this->removePluginFromAppComposer($pluginModel->composer_package_name, $pluginPath);

        // 3. Delete plugin directory
        if (File::exists($pluginPath)) {
            File::deleteDirectory($pluginPath);
            Log::info("Plugin directory '{$pluginPath}' deleted for '{$pluginSlug}'.");
        }

        // 4. Delete plugin record from database
        $pluginModel->delete(); // Use renamed Model class
        Log::info("Plugin '{$pluginSlug}' uninstalled and removed from database.");

        // 5. Invalidate plugin caches specifically
        $this->cacheService->flushByTags(['plugins', 'discovery']);

        // 6. Clear caches and regenerate autoloader one last time
        $this->runComposerDumpAutoload();
        $this->clearCaches();
    }
}
