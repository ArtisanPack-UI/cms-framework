<?php

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Managers;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginInstallationException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginNotFoundException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginValidationException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Composer\Autoload\ClassLoader;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class PluginManager
{
    private ClassLoader $classLoader;

    public function __construct()
    {
        // Get Composer's ClassLoader instance from registered autoloaders
        $this->classLoader = $this->getComposerClassLoader();
    }

    /**
     * Discover all plugins from filesystem and database.
     *
     * Scans the plugins directory for valid plugin.json manifests,
     * merges with database records to include activation status.
     * Results are cached for performance.
     *
     * @return array Array of plugin data with keys: slug, name, version,
     *               description, author, is_active, path, manifest
     */
    public function discoverPlugins(): array
    {
        if ( config( 'cms.plugins.cacheEnabled' ) ) {
            return Cache::remember(
                config( 'cms.plugins.cacheKey' ),
                config( 'cms.plugins.cacheTtl' ),
                fn () => $this->scanPluginsDirectory(),
            );
        }

        return $this->scanPluginsDirectory();
    }

    /**
     * Get a specific plugin by slug.
     *
     * Validates slug format and ensures path is within plugins directory
     * to prevent path traversal attacks.
     *
     * @param  string  $slug  Plugin slug (validated)
     *
     * @return array|null Plugin data or null if not found
     */
    public function getPlugin( string $slug ): ?array
    {
        // Validate slug format (alphanumeric, hyphens, underscores only)
        if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $slug ) ) {
            return null;
        }

        // Build and validate path
        $pluginsBasePath = $this->getPluginsPath();
        $pluginPath      = $pluginsBasePath . '/' . $slug;

        // Resolve real path and verify it's within plugins directory
        $realPluginPath = realpath( $pluginPath );
        if ( false === $realPluginPath ) {
            return null;
        }

        $realBasePath = realpath( $pluginsBasePath );
        if ( false === $realBasePath || 0 !== strpos( $realPluginPath, $realBasePath . DIRECTORY_SEPARATOR ) ) {
            return null; // Path traversal attempt detected
        }

        // Check if plugin.json exists
        $manifestPath = $realPluginPath . '/plugin.json';
        if ( ! File::exists( $manifestPath ) ) {
            return null;
        }

        // Parse manifest
        $manifest = $this->parseManifest( $manifestPath );
        if ( null === $manifest ) {
            return null;
        }

        // Get database record if exists
        $dbPlugin = Plugin::where( 'slug', sanitizeText( $slug ) )->first();

        return [
            'slug'        => $slug,
            'name'        => $manifest['name'] ?? $slug,
            'version'     => $manifest['version'] ?? '0.0.0',
            'description' => $manifest['description'] ?? '',
            'author'      => $manifest['author'] ?? '',
            'is_active'   => $dbPlugin ? $dbPlugin->is_active : false,
            'path'        => $realPluginPath,
            'manifest'    => $manifest,
        ];
    }

    /**
     * Install plugin from ZIP file.
     *
     * Process:
     * 1. Validate ZIP file (MIME type, size, integrity)
     * 2. Extract to temporary location
     * 3. Validate plugin.json manifest
     * 4. Move to plugins directory
     * 5. Register in database
     * 6. Fire installation hooks
     *
     * @param  string  $zipPath  Absolute path to uploaded ZIP file
     *
     * @throws PluginValidationException If ZIP or manifest is invalid
     * @throws PluginInstallationException If extraction or registration fails
     *
     * @return Plugin The installed plugin model
     */
    public function installFromZip( string $zipPath ): Plugin
    {
        $this->validateZip( $zipPath );

        $slug         = $this->extractZip( $zipPath );
        $manifestPath = $this->getPluginsPath() . '/' . $slug . '/plugin.json';
        $manifest     = $this->parseManifest( $manifestPath );

        $this->validateManifest( $manifest );

        // Check if already installed
        if ( Plugin::where( 'slug', sanitizeText( $slug ) )->exists() ) {
            throw PluginInstallationException::alreadyInstalled( $slug );
        }

        doAction( 'plugin.installing', $slug );

        // Register in database
        $plugin = Plugin::create( [
            'slug'             => $slug,
            'name'             => $manifest['name'],
            'version'          => $manifest['version'],
            'is_active'        => false,
            'service_provider' => $manifest['service_provider'] ?? null,
            'meta'             => $manifest,
            'installed_at'     => now(),
        ] );

        $this->clearCaches();

        doAction( 'plugin.installed', $slug, $plugin );

        return $plugin;
    }

    /**
     * Activate a plugin.
     *
     * Process:
     * 1. Find plugin in database
     * 2. Register PSR-4 autoloader
     * 3. Run migrations (if any)
     * 4. Register service provider
     * 5. Update database (is_active = true)
     * 6. Fire activation hooks
     *
     * @param  string  $slug  Plugin slug
     *
     * @throws PluginNotFoundException If plugin doesn't exist
     *
     * @return bool True on success
     */
    public function activate( string $slug ): bool
    {
        $plugin = Plugin::where( 'slug', sanitizeText( $slug ) )->first();

        if ( ! $plugin ) {
            throw PluginNotFoundException::forSlug( $slug );
        }

        doAction( 'plugin.activating', $slug );

        DB::transaction( function () use ( $plugin ): void {
            // Register autoloader
            if ( isset( $plugin->meta['autoload'] ) ) {
                $this->registerAutoloader( $plugin->slug, $plugin->meta['autoload'] );
            }

            // Run migrations
            if ( isset( $plugin->meta['migrations_path'] ) ) {
                $this->runMigrations( $plugin->slug, $plugin->meta['migrations_path'] );
            }

            // Register service provider
            if ( $plugin->hasServiceProvider() ) {
                app()->register( $plugin->service_provider );
            }

            // Mark as active
            $plugin->is_active = true;
            $plugin->save();
        } );

        $this->clearCaches();

        doAction( 'plugin.activated', $slug, $plugin );

        return true;
    }

    /**
     * Deactivate a plugin.
     *
     * Process:
     * 1. Find plugin in database
     * 2. Fire deactivation hooks (plugin can cleanup here)
     * 3. Update database (is_active = false)
     * 4. Clear caches
     *
     * Note: Does NOT rollback migrations. Plugin handles cleanup via hooks.
     *
     * @param  string  $slug  Plugin slug
     *
     * @return bool True on success
     */
    public function deactivate( string $slug ): bool
    {
        $plugin = Plugin::where( 'slug', sanitizeText( $slug ) )->first();

        if ( ! $plugin ) {
            throw PluginNotFoundException::forSlug( $slug );
        }

        doAction( 'plugin.deactivating', $slug );

        $plugin->is_active = false;
        $plugin->save();

        $this->clearCaches();

        doAction( 'plugin.deactivated', $slug );

        return true;
    }

    /**
     * Delete a plugin.
     *
     * Process:
     * 1. Deactivate if active
     * 2. Fire deletion hooks
     * 3. Remove from database
     * 4. Remove from filesystem (if $deleteFiles = true)
     * 5. Clear caches
     *
     * @param  string  $slug  Plugin slug
     * @param  bool  $deleteFiles  Whether to delete plugin files
     *
     * @throws PluginNotFoundException If plugin doesn't exist
     *
     * @return bool True on success
     */
    public function delete( string $slug, bool $deleteFiles = true ): bool
    {
        $plugin = Plugin::where( 'slug', sanitizeText( $slug ) )->first();

        if ( ! $plugin ) {
            throw PluginNotFoundException::forSlug( $slug );
        }

        // Deactivate if active
        if ( $plugin->is_active ) {
            $this->deactivate( $slug );
        }

        doAction( 'plugin.deleting', $slug );

        // Remove from database
        $plugin->delete();

        // Remove from filesystem
        if ( $deleteFiles ) {
            $pluginPath = $this->getPluginsPath() . '/' . $slug;
            if ( File::exists( $pluginPath ) ) {
                File::deleteDirectory( $pluginPath );
            }
        }

        $this->clearCaches();

        doAction( 'plugin.deleted', $slug );

        return true;
    }

    /**
     * Load all active plugins during application boot.
     *
     * This method is called EARLY in the boot process by PluginsServiceProvider.
     * It registers autoloaders and service providers for all active plugins.
     */
    public function loadActivePlugins(): void
    {
        $activePlugins = Plugin::active()->get();

        foreach ( $activePlugins as $plugin ) {
            // Register autoloader
            if ( isset( $plugin->meta['autoload'] ) ) {
                $this->registerAutoloader( $plugin->slug, $plugin->meta['autoload'] );
            }

            // Register service provider
            if ( $plugin->hasServiceProvider() ) {
                try {
                    app()->register( $plugin->service_provider );
                } catch ( Exception $e ) {
                    // Log error but don't break application
                    logger()->error( "Failed to register plugin service provider: {$plugin->slug}", [
                        'exception' => $e->getMessage(),
                    ] );
                }
            }
        }
    }

    /**
     * Run plugin migrations.
     *
     * @param  string  $slug  Plugin slug
     * @param  string  $migrationsPath  Relative path to migrations directory
     */
    protected function runMigrations( string $slug, string $migrationsPath ): void
    {
        $fullPath = $this->getPluginsPath() . '/' . $slug . '/' . $migrationsPath;

        if ( ! File::isDirectory( $fullPath ) ) {
            return;
        }

        // Run migrations using Artisan
        Artisan::call( 'migrate', [
            '--path'  => str_replace( base_path(), '', $fullPath ),
            '--force' => true,
        ] );
    }

    /**
     * Register plugin PSR-4 autoloader.
     *
     * @param  string  $slug  Plugin slug
     * @param  array  $autoloadConfig  Autoload configuration from plugin.json
     */
    protected function registerAutoloader( string $slug, array $autoloadConfig ): void
    {
        if ( ! isset( $autoloadConfig['psr-4'] ) ) {
            return;
        }

        $pluginPath = $this->getPluginsPath() . '/' . $slug;

        foreach ( $autoloadConfig['psr-4'] as $namespace => $path ) {
            $this->classLoader->addPsr4(
                $namespace,
                $pluginPath . '/' . $path,
            );
        }

        // Re-register the autoloader
        $this->classLoader->register( true );
    }

    /**
     * Validate plugin.json manifest.
     *
     * @param  array  $manifest  Parsed plugin.json data
     *
     * @throws PluginValidationException If validation fails
     */
    protected function validateManifest( array $manifest ): void
    {
        // Check required fields
        $required = ['slug', 'name', 'version'];

        foreach ( $required as $field ) {
            if ( ! isset( $manifest[ $field ] ) || empty( $manifest[ $field ] ) ) {
                throw PluginValidationException::invalidManifest( "Missing required field: {$field}" );
            }
        }

        // Validate slug format
        if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $manifest['slug'] ) ) {
            throw PluginValidationException::invalidManifest( 'Invalid slug format. Use alphanumeric, hyphens, and underscores only.' );
        }

        // Validate version format (basic semver check)
        // Anchored at end to prevent injection attempts like "1.0.0'; DROP TABLE"
        if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $manifest['version'] ) ) {
            throw PluginValidationException::invalidManifest( 'Invalid version format. Use semantic versioning (e.g., 1.0.0).' );
        }
    }

    /**
     * Validate ZIP file before extraction.
     *
     * @param  string  $zipPath  Path to ZIP file
     *
     * @throws PluginValidationException If ZIP is invalid
     */
    protected function validateZip( string $zipPath ): void
    {
        // Check file exists
        if ( ! File::exists( $zipPath ) ) {
            throw PluginValidationException::invalidZip( 'ZIP file not found' );
        }

        // Check MIME type
        $mimeType     = mime_content_type( $zipPath );
        $allowedTypes = config( 'cms.plugins.allowedMimeTypes' );

        if ( ! in_array( $mimeType, $allowedTypes ) ) {
            throw PluginValidationException::invalidZip( 'Invalid file type. Must be a ZIP file.' );
        }

        // Check file size
        $maxSize = config( 'cms.plugins.maxUploadSize' );
        if ( filesize( $zipPath ) > $maxSize ) {
            throw PluginValidationException::invalidZip( 'File size exceeds maximum allowed size.' );
        }

        // Validate ZIP integrity
        $zip = new ZipArchive;
        if ( true !== $zip->open( $zipPath ) ) {
            throw PluginValidationException::invalidZip( 'Invalid or corrupted ZIP file.' );
        }

        // Check for plugin.json in ZIP
        $manifestFound = false;
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $filename = $zip->getNameIndex( $i );
            if ( str_ends_with( $filename, 'plugin.json' ) ) {
                $manifestFound = true;
                break;
            }
        }

        $zip->close();

        if ( ! $manifestFound ) {
            throw PluginValidationException::invalidZip( 'Plugin manifest (plugin.json) not found in ZIP.' );
        }
    }

    /**
     * Extract ZIP file to plugins directory.
     *
     * @param  string  $zipPath  Path to ZIP file
     *
     * @throws PluginInstallationException If extraction fails
     *
     * @return string Plugin slug
     */
    protected function extractZip( string $zipPath ): string
    {
        $zip = new ZipArchive;
        if ( true !== $zip->open( $zipPath ) ) {
            throw PluginInstallationException::extractionFailed( 'unknown' );
        }

        // Get the first directory name (plugin slug)
        $firstEntry = $zip->getNameIndex( 0 );
        $slug       = explode( '/', $firstEntry )[0];

        // Extract to plugins directory
        $extractPath = $this->getPluginsPath();
        if ( ! $zip->extractTo( $extractPath ) ) {
            $zip->close();
            throw PluginInstallationException::extractionFailed( $slug );
        }

        $zip->close();

        return $slug;
    }

    /**
     * Parse plugin.json manifest file.
     *
     * @param  string  $manifestPath  Path to plugin.json
     *
     * @return array|null Parsed manifest or null if invalid
     */
    protected function parseManifest( string $manifestPath ): ?array
    {
        if ( ! File::exists( $manifestPath ) ) {
            return null;
        }

        $content  = File::get( $manifestPath );
        $manifest = json_decode( $content, true );

        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return null;
        }

        return $manifest;
    }

    /**
     * Scan plugins directory for all plugins.
     *
     * @return array Array of plugin data
     */
    protected function scanPluginsDirectory(): array
    {
        $pluginsPath = $this->getPluginsPath();
        $plugins     = [];

        if ( ! File::isDirectory( $pluginsPath ) ) {
            return $plugins;
        }

        $directories = File::directories( $pluginsPath );

        foreach ( $directories as $directory ) {
            $slug         = basename( $directory );
            $manifestPath = $directory . '/plugin.json';

            if ( ! File::exists( $manifestPath ) ) {
                continue;
            }

            $manifest = $this->parseManifest( $manifestPath );
            if ( null === $manifest ) {
                continue;
            }

            // Get database record if exists
            $dbPlugin = Plugin::where( 'slug', sanitizeText( $slug ) )->first();

            $plugins[] = [
                'slug'        => $slug,
                'name'        => $manifest['name'] ?? $slug,
                'version'     => $manifest['version'] ?? '0.0.0',
                'description' => $manifest['description'] ?? '',
                'author'      => $manifest['author'] ?? '',
                'is_active'   => $dbPlugin ? $dbPlugin->is_active : false,
                'path'        => $directory,
                'manifest'    => $manifest,
            ];
        }

        return $plugins;
    }

    /**
     * Get plugins directory path.
     *
     * @return string Full path to plugins directory
     */
    protected function getPluginsPath(): string
    {
        return base_path( config( 'cms.plugins.directory', 'plugins' ) );
    }

    /**
     * Clear all plugin-related caches.
     */
    protected function clearCaches(): void
    {
        Cache::forget( config( 'cms.plugins.cacheKey' ) );
    }

    /**
     * Get Composer's ClassLoader from SPL autoload functions.
     *
     *
     * @throws RuntimeException If ClassLoader not found
     */
    private function getComposerClassLoader(): ClassLoader
    {
        foreach ( spl_autoload_functions() as $autoloader ) {
            if ( is_array( $autoloader ) && $autoloader[0] instanceof ClassLoader ) {
                return $autoloader[0];
            }
        }

        throw new RuntimeException( 'Composer ClassLoader not found in registered autoloaders');
    }
}
