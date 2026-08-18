<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Managers;

use ArtisanPackUI\CMSFramework\Modules\Core\Managers\Concerns\HasManifestParsing;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\ExtensionArchive;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\CircularDependencyException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\DependencyNotSatisfiedException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\IncompatiblePluginException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginConflictException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginInstallationException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginNotFoundException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginValidationException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\DependencyResolver;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\DependencyResult;
use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use ZipArchive;

class PluginManager
{
    use HasManifestParsing;

    private ClassLoader $classLoader;

    /**
     * Resolve Composer's runtime ClassLoader so discovered plugins can register
     * their own PSR-4 prefixes.
     */
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
        if ( ! $this->validateSlug( $slug ) ) {
            return null;
        }

        // Build and validate path within plugins directory
        $pluginsBasePath = $this->getPluginsPath();
        $realPluginPath  = $this->resolveSecurePath( $pluginsBasePath . '/' . $slug, $pluginsBasePath );

        if ( null === $realPluginPath ) {
            return null;
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

        // `validateManifest()` already enforced that a non-empty, well-formed
        // `slug` is present. Now make sure it matches the extracted directory
        // name. The row's `slug` is derived from the directory while `meta` is
        // seated verbatim from the manifest, so a divergence lets a plugin
        // declare — and later, on uninstall, remove — permission rows namespaced
        // under a different plugin's slug. Mirror the Themes module, which
        // asserts the same match after extraction.
        if ( $manifest['slug'] !== $slug ) {
            File::deleteDirectory( $this->getPluginsPath() . '/' . $slug );

            throw PluginValidationException::invalidManifest(
                "Manifest slug '{$manifest['slug']}' must match extracted directory slug '{$slug}'.",
            );
        }

        // Check if already installed
        if ( Plugin::where( 'slug', sanitizeText( $slug ) )->exists() ) {
            throw PluginInstallationException::alreadyInstalled( $slug );
        }

        doAction( 'ap.cmsFramework.plugin.installing', $slug );

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

        doAction( 'ap.cmsFramework.plugin.installed', $slug, $plugin );

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

        // Host-version compatibility gate (#183). Runs before any state mutation.
        $this->assertHostVersionCompatible( $plugin );

        // Plugin dependency + conflict gate (#45). Also runs before any state
        // mutation so a plugin missing its dependencies never half-activates.
        $this->assertDependenciesSatisfied( $plugin );

        doAction( 'ap.cmsFramework.plugin.activating', $slug );

        $priorPsr4              = [];
        $migrationsAttempted    = false;
        $serviceProviderStarted = false;

        try {
            DB::transaction( function () use (
                $plugin,
                &$priorPsr4,
                &$migrationsAttempted,
                &$serviceProviderStarted,
            ): void {
                if ( isset( $plugin->meta['autoload'] ) ) {
                    // Snapshot the PSR-4 map BEFORE adding, so rollback can
                    // restore prior paths for shared namespaces instead of
                    // wiping them with setPsr4($ns, []).
                    $priorPsr4 = $this->snapshotPsr4( $plugin->meta['autoload']['psr-4'] ?? [] );
                    $this->registerAutoloader( $plugin->slug, $plugin->meta['autoload'] );
                }

                if ( isset( $plugin->meta['migrations_path'] ) ) {
                    // Flip BEFORE the Artisan call so a mid-migration failure
                    // (DDL auto-commits in MySQL/MariaDB) still triggers a
                    // rollback attempt in the catch block.
                    $migrationsAttempted = true;
                    $this->runMigrations( $plugin->slug, $plugin->meta['migrations_path'] );
                }

                if ( $plugin->hasServiceProvider() ) {
                    app()->register( $plugin->service_provider );
                    $serviceProviderStarted = true;
                }

                $this->seedPermissions( $plugin );

                $plugin->is_active = true;
                $plugin->save();
            } );
        } catch ( Throwable $e ) {
            // Roll back any partial activation state (#182).
            $this->rollbackFailedActivation( $plugin, $priorPsr4, $migrationsAttempted );

            throw $e;
        }

        if ( config( 'cms.plugins.autoClearFrameworkCaches', false ) ) {
            $this->clearFrameworkCaches();
        }

        $this->clearCaches();

        // Announce the plugin's hooks are online. Fires AFTER the transaction
        // commits so a rolled-back activation does not leak a `.hookRegistered`
        // event to subscribers — mirrors the placement of `.activated` below.
        // The service provider's `register()` / `boot()` is where a plugin
        // typically calls addAction/addFilter; the manifest's optional `hooks`
        // field lets plugin authors enumerate what came online for observers,
        // and an empty array still fires so every activation emits a per-plugin
        // signal.
        if ( $serviceProviderStarted ) {
            $declaredHooks = is_array( $plugin->meta['hooks'] ?? null )
                ? $plugin->meta['hooks']
                : [];

            doAction(
                'ap.cmsFramework.plugin.hookRegistered',
                $plugin->slug,
                $declaredHooks,
            );
        }

        doAction( 'ap.cmsFramework.plugin.activated', $slug, $plugin );

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
     * @param  bool  $force  Skip the active-dependents guard (used by delete())
     *
     * @throws PluginNotFoundException If plugin doesn't exist
     * @throws DependencyNotSatisfiedException If active plugins still depend on it and $force is false
     *
     * @return bool True on success
     */
    public function deactivate( string $slug, bool $force = false ): bool
    {
        $plugin = Plugin::where( 'slug', sanitizeText( $slug ) )->first();

        if ( ! $plugin ) {
            throw PluginNotFoundException::forSlug( $slug );
        }

        // Dependent-plugin guard (#45). Refuse to pull a dependency out from
        // under still-active plugins unless the caller explicitly forces it.
        if ( ! $force ) {
            $activeDependents = $this->activeDependents( $slug );
            if ( ! empty( $activeDependents ) ) {
                throw DependencyNotSatisfiedException::hasActiveDependents( $slug, $activeDependents );
            }
        }

        doAction( 'ap.cmsFramework.plugin.deactivating', $slug );

        $plugin->is_active = false;
        $plugin->save();

        if ( config( 'cms.plugins.autoClearFrameworkCaches', false ) ) {
            $this->clearFrameworkCaches();
        }
        $this->clearCaches();

        doAction( 'ap.cmsFramework.plugin.deactivated', $slug );

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

        // Deactivate if active. Forced past the dependents guard: deletion is a
        // deliberate teardown, and blocking it here would leave the plugin
        // half-removed. Dependents are surfaced by getDependents() for the UI.
        if ( $plugin->is_active ) {
            // Record any still-active dependents so an operator can trace a
            // later failure back to this forced removal.
            $orphaned = $this->activeDependents( $slug );
            if ( ! empty( $orphaned ) ) {
                logger()->warning( "Deleting plugin '{$slug}' leaves active dependents unsatisfied.", [
                    'dependents' => $orphaned,
                ] );
            }

            $this->deactivate( $slug, true );
        }

        doAction( 'ap.cmsFramework.plugin.deleting', $slug );

        // Opt-in migration rollback (#182). Guarded by manifest flag so hosts don't
        // accidentally drop plugin-owned data.
        if ( $plugin->rollback_migrations_on_delete && isset( $plugin->meta['migrations_path'] ) ) {
            try {
                $this->rollbackMigrations( $plugin->slug, $plugin->meta['migrations_path'] );
            } catch ( Throwable $e ) {
                logger()->error( "Failed to rollback migrations for plugin: {$slug}", [
                    'exception' => $e->getMessage(),
                ] );
            }
        }

        // Remove seeded permissions (#182).
        $this->removeSeededPermissions( $plugin );

        // Remove from database
        $plugin->delete();

        // Remove from filesystem
        if ( $deleteFiles ) {
            $pluginPath = $this->getPluginsPath() . '/' . $slug;
            if ( File::exists( $pluginPath ) ) {
                File::deleteDirectory( $pluginPath );
            }
        }

        if ( config( 'cms.plugins.autoClearFrameworkCaches', false ) ) {
            $this->clearFrameworkCaches();
        }
        $this->clearCaches();

        doAction( 'ap.cmsFramework.plugin.deleted', $slug );

        return true;
    }

    /**
     * Load all active plugins during application boot.
     *
     * This method is called EARLY in the boot process by PluginsServiceProvider.
     * It registers autoloaders and service providers for all active plugins,
     * dependencies first (#45) so a dependent's service provider never boots
     * before the provider whose services it consumes.
     */
    public function loadActivePlugins(): void
    {
        $activePlugins = Plugin::active()->get()->keyBy( 'slug' );

        // Build the graph once and thread it through the ordering so booting
        // plugins costs a single Plugin::all() query rather than two.
        $graph = $this->buildDependencyGraph();

        foreach ( $this->activeLoadOrder( $activePlugins->keys()->all(), $graph ) as $slug ) {
            $plugin = $activePlugins->get( $slug );

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
     * Check a plugin's dependencies, version constraints, and conflicts against
     * the currently installed plugins (#45).
     *
     * @since 2.9.0
     *
     * @param  string  $slug  Plugin slug
     * @param  array<string,array<string,mixed>>|null  $graph  Pre-built graph to reuse, or null to build one.
     *
     * @return DependencyResult The buckets of unmet requirements.
     */
    public function checkDependencies( string $slug, ?array $graph = null ): DependencyResult
    {
        $graph ??= $this->buildDependencyGraph();

        // A plugin present on disk but not registered in the database has no
        // graph entry; seed its declared requires/conflicts from the manifest
        // so its status reflects what the manifest actually asks for, rather
        // than resolving an empty target to "satisfied".
        if ( ! isset( $graph[ $slug ] ) ) {
            $manifestEntry = $this->manifestGraphEntry( $slug );
            if ( null !== $manifestEntry ) {
                $graph[ $slug ] = $manifestEntry;
            }
        }

        return $this->dependencyResolver()->resolve(
            $slug,
            $graph,
            $this->resolveHostFrameworkVersion(),
        );
    }

    /**
     * List the slugs of installed plugins that declare a requirement on $slug.
     *
     * @since 2.9.0
     *
     * @param  string  $slug  The depended-upon plugin.
     * @param  array<string,array<string,mixed>>|null  $graph  Pre-built graph to reuse, or null to build one.
     *
     * @return array<int,string> Dependent plugin slugs.
     */
    public function getDependents( string $slug, ?array $graph = null ): array
    {
        return $this->dependencyResolver()->dependents( $slug, $graph ?? $this->buildDependencyGraph() );
    }

    /**
     * Resolve a dependency-first activation order for the given slugs (#45).
     *
     * @since 2.9.0
     *
     * @param  array<int,string>  $slugs  Plugins the caller wants to activate.
     * @param  array<string,array<string,mixed>>|null  $graph  Pre-built graph to reuse, or null to build one.
     *
     * @throws CircularDependencyException When a cycle exists.
     *
     * @return array<int,string> Slugs ordered dependencies-first.
     */
    public function getActivationOrder( array $slugs, ?array $graph = null ): array
    {
        return $this->dependencyResolver()->activationOrder( $slugs, $graph ?? $this->buildDependencyGraph() );
    }

    /**
     * Whether a plugin can be deactivated without breaking active dependents.
     *
     * @since 2.9.0
     *
     * @param  string  $slug  Plugin slug
     * @param  array<string,array<string,mixed>>|null  $graph  Pre-built graph to reuse, or null to build one.
     *
     * @return bool False when at least one active plugin depends on it.
     */
    public function canDeactivate( string $slug, ?array $graph = null ): bool
    {
        return empty( $this->activeDependents( $slug, $graph ) );
    }

    /**
     * Whether a manifest `update` value passes the shared source rules.
     *
     * The boolean counterpart to {@see validateUpdateSourceManifestField()},
     * mirroring `ThemeManager::isUsableUpdateSource()`. `UpdateManager` calls it
     * at the point of use to re-validate a value read back from an installed
     * plugin's manifest — defense-in-depth against a manifest seated by a
     * pre-#283 framework version, or edited in place on disk, that never passed
     * through `validateManifest()`.
     *
     * @since 2.8.0
     *
     * @param  mixed  $update  Raw `update` value from the manifest.
     *
     * @return bool True when the value is a usable update source.
     */
    public function isUsableUpdateSource( mixed $update ): bool
    {
        return null === $this->checkUpdateSourceManifestField( $update );
    }

    /**
     * Re-run the install-time manifest rules against a manifest read back from
     * an already-installed plugin.
     *
     * `UpdateManager::updatePlugin()` re-seats `meta` straight from the manifest
     * inside a downloaded ZIP, and every security-relevant check in
     * `validateManifest()` — the `migrations_path` traversal guard, the
     * permission-prefix guard, the `update` source rules — otherwise runs only
     * at install (#283). This is the public entry point that lets the update
     * path, which lives in a separate class, enforce those same rules before it
     * trusts the new manifest. Mirrors `isUsableUpdateSource()` exposing the
     * shared source rules to `UpdateManager`.
     *
     * @since 2.9.0
     *
     * @param  array  $manifest  Parsed plugin.json from the downloaded update.
     *
     * @throws PluginValidationException If the manifest violates the schema rules.
     */
    public function assertManifestValid( array $manifest ): void
    {
        $this->validateManifest( $manifest );
    }

    /**
     * Build the normalized dependency graph from installed plugin records.
     *
     * Keyed by slug; each entry carries the version, activation state, and the
     * declared requires/conflicts the resolver operates on. Public so callers
     * that make several dependency queries in one request can build the graph
     * once and pass it back in, avoiding a rebuild per call.
     *
     * @since 2.9.0
     *
     * @return array<string,array<string,mixed>>
     */
    public function buildDependencyGraph(): array
    {
        $graph = [];

        foreach ( Plugin::all() as $plugin ) {
            $graph[ $plugin->slug ] = [
                'version'       => ( string ) $plugin->version,
                'is_active'     => ( bool ) $plugin->is_active,
                'requires'      => $plugin->required_plugins,
                'requires_host' => $plugin->required_host_version,
                'conflicts'     => $plugin->conflicting_plugins,
            ];
        }

        return $graph;
    }

    /**
     * Order active plugin slugs dependencies-first for boot registration.
     *
     * Falls back to the given order if a dependency cycle is detected, and
     * appends any active slug the resolver omitted (its transitive deps may
     * pull in inactive plugins, which are filtered out here) so every active
     * plugin is still loaded exactly once.
     *
     * @since 2.9.0
     *
     * @param  array<int,string>  $activeSlugs  Slugs of the active plugins.
     * @param  array<string,array<string,mixed>>|null  $graph  Pre-built graph to reuse, or null to build one.
     *
     * @return array<int,string> The same slugs, dependencies first.
     */
    protected function activeLoadOrder( array $activeSlugs, ?array $graph = null ): array
    {
        $active = array_fill_keys( $activeSlugs, true );

        try {
            $resolved = $this->getActivationOrder( $activeSlugs, $graph );
        } catch ( CircularDependencyException $e ) {
            logger()->warning( 'Circular plugin dependency detected while ordering active plugins for boot; falling back to unordered load.', [
                'exception' => $e->getMessage(),
            ] );

            return $activeSlugs;
        }

        $ordered = [];
        foreach ( $resolved as $slug ) {
            if ( isset( $active[ $slug ] ) ) {
                $ordered[]       = $slug;
                $active[ $slug ] = false;
            }
        }

        // Defensive: append any active slug the resolver never emitted.
        foreach ( $activeSlugs as $slug ) {
            if ( ! empty( $active[ $slug ] ) ) {
                $ordered[] = $slug;
            }
        }

        return $ordered;
    }

    /**
     * Run plugin migrations.
     *
     * @param  string  $slug  Plugin slug
     * @param  string  $migrationsPath  Relative path to migrations directory
     */
    protected function runMigrations( string $slug, string $migrationsPath ): void
    {
        $safePath = $this->resolveSecureMigrationsPath( $slug, $migrationsPath );
        if ( null === $safePath ) {
            return;
        }

        // Run migrations using Artisan
        Artisan::call( 'migrate', [
            '--path'  => str_replace( base_path(), '', $safePath ),
            '--force' => true,
        ] );
    }

    /**
     * Resolve a plugin-declared migrations path and confirm it lives inside the
     * plugin's own directory. Defense-in-depth against a malicious manifest
     * whose migrations_path escaped via ".." (the schema validator also
     * rejects those, but that runs at install time only).
     */
    protected function resolveSecureMigrationsPath( string $slug, string $migrationsPath ): ?string
    {
        $pluginDir = $this->getPluginsPath() . '/' . $slug;
        $fullPath  = $pluginDir . '/' . ltrim( $migrationsPath, '/' );

        if ( ! File::isDirectory( $fullPath ) ) {
            return null;
        }

        $realFull   = realpath( $fullPath );
        $realPlugin = realpath( $pluginDir );

        if ( false === $realFull || false === $realPlugin ) {
            return null;
        }

        // The resolved path must sit inside the plugin's own directory.
        if ( ! str_starts_with( $realFull . DIRECTORY_SEPARATOR, $realPlugin . DIRECTORY_SEPARATOR ) ) {
            logger()->warning( "Plugin '{$slug}' migrations_path resolved outside plugin dir; refusing to touch: {$realFull}" );

            return null;
        }

        return $realFull;
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
        if ( ! $this->validateSlug( $manifest['slug'] ) ) {
            throw PluginValidationException::invalidManifest( 'Invalid slug format. Use alphanumeric, hyphens, and underscores only.' );
        }

        // Validate version format (basic semver check)
        // Anchored at end to prevent injection attempts like "1.0.0'; DROP TABLE"
        if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $manifest['version'] ) ) {
            throw PluginValidationException::invalidManifest( 'Invalid version format. Use semantic versioning (e.g., 1.0.0).' );
        }

        $this->validateOptionalManifestFields( $manifest );
    }

    /**
     * Validate optional manifest fields introduced in the v2.4 schema extension.
     *
     * @throws PluginValidationException If any optional field is malformed.
     */
    protected function validateOptionalManifestFields( array $manifest ): void
    {
        if ( isset( $manifest['min_host_version'] ) ) {
            if ( ! is_string( $manifest['min_host_version'] )
                || ! preg_match( '/^\d+\.\d+\.\d+$/', $manifest['min_host_version'] ) ) {
                throw PluginValidationException::invalidManifest( 'Invalid min_host_version format. Use semantic versioning (e.g., 1.0.0).' );
            }
        }

        if ( isset( $manifest['federated_module'] ) ) {
            $federated = $manifest['federated_module'];
            if ( ! is_array( $federated )
                || empty( $federated['entry'] )
                || ! is_string( $federated['entry'] ) ) {
                throw PluginValidationException::invalidManifest( 'Invalid federated_module. Must be an object with a non-empty string "entry".' );
            }
            if ( isset( $federated['exposes'] ) && ! is_array( $federated['exposes'] ) ) {
                throw PluginValidationException::invalidManifest( 'Invalid federated_module.exposes. Must be an array of module names.' );
            }
        }

        if ( isset( $manifest['nav_entries'] ) ) {
            if ( ! is_array( $manifest['nav_entries'] ) || false === array_is_list( $manifest['nav_entries'] ) ) {
                throw PluginValidationException::invalidManifest( 'Invalid nav_entries. Must be a list of entry objects.' );
            }
            foreach ( $manifest['nav_entries'] as $index => $entry ) {
                if ( ! is_array( $entry ) || empty( $entry['slug'] ) || empty( $entry['label'] ) ) {
                    throw PluginValidationException::invalidManifest( "Invalid nav_entries[{$index}]. Each entry needs a slug and a label." );
                }
            }
        }

        if ( isset( $manifest['permissions'] ) ) {
            if ( ! is_array( $manifest['permissions'] ) || false === array_is_list( $manifest['permissions'] ) ) {
                throw PluginValidationException::invalidManifest( 'Invalid permissions. Must be a list of permission slugs.' );
            }
            // Enforce a plugin-slug namespace prefix so seed/remove can't touch
            // framework-owned or other-plugin permissions. Prevents a manifest
            // like `permissions: ["manage_users"]` from wiping core rows on
            // uninstall.
            $prefix = $manifest['slug'] . '.';
            foreach ( $manifest['permissions'] as $index => $permission ) {
                if ( ! is_string( $permission ) || '' === trim( $permission ) ) {
                    throw PluginValidationException::invalidManifest( "Invalid permissions[{$index}]. Each permission must be a non-empty string." );
                }
                if ( ! str_starts_with( $permission, $prefix ) ) {
                    throw PluginValidationException::invalidManifest(
                        "Invalid permissions[{$index}]. Plugin permissions must be prefixed with '{$prefix}' (got '{$permission}').",
                    );
                }
            }
        }

        if ( isset( $manifest['migrations_path'] ) ) {
            $migrationsPath = $manifest['migrations_path'];
            if ( ! is_string( $migrationsPath ) || '' === $migrationsPath ) {
                throw PluginValidationException::invalidManifest( 'Invalid migrations_path. Must be a non-empty relative string.' );
            }
            // Reject any traversal or absolute-path attempts. runMigrations()
            // resolves this against the plugin directory; a value like
            // "../../database/migrations" would otherwise re-run/rollback
            // framework migrations.
            if ( str_starts_with( $migrationsPath, '/' )
                || str_contains( $migrationsPath, '..' )
                || 1 === preg_match( '/^[A-Za-z]:/', $migrationsPath ) ) {
                throw PluginValidationException::invalidManifest( 'Invalid migrations_path. Must be a relative path inside the plugin directory (no "..", no absolute paths).' );
            }
        }

        if ( isset( $manifest['update'] ) ) {
            $this->validateUpdateSourceManifestField( $manifest['update'] );
        }

        if ( isset( $manifest['requires'] ) ) {
            $this->validateRequiresManifestField( $manifest['requires'], $manifest['slug'] );
        }

        if ( isset( $manifest['conflicts'] ) ) {
            $this->validateConstraintMapManifestField( $manifest['conflicts'], 'conflicts', $manifest['slug'] );
        }
    }

    /**
     * Validate the optional `requires` manifest key introduced for plugin
     * dependency management (#45).
     *
     * Shape:
     *     "requires": {
     *         "cms-framework": "^2.0",
     *         "plugins": { "base-forms": "^1.5" }
     *     }
     *
     * @param  mixed  $requires  Raw manifest value.
     * @param  string  $ownSlug  The declaring plugin's own slug.
     *
     * @throws PluginValidationException If the block is malformed.
     */
    protected function validateRequiresManifestField( mixed $requires, string $ownSlug ): void
    {
        if ( ! is_array( $requires ) ) {
            throw PluginValidationException::invalidManifest( 'Invalid requires. Must be an object.' );
        }

        if ( isset( $requires['cms-framework'] ) ) {
            $constraint = $requires['cms-framework'];
            if ( ! is_string( $constraint ) || '' === trim( $constraint ) ) {
                throw PluginValidationException::invalidManifest( 'Invalid requires.cms-framework. Must be a non-empty version constraint string.' );
            }
        }

        if ( isset( $requires['plugins'] ) ) {
            $this->validateConstraintMapManifestField( $requires['plugins'], 'requires.plugins', $ownSlug );
        }
    }

    /**
     * Validate a manifest field that maps plugin slugs to version constraints
     * (`requires.plugins` and `conflicts`).
     *
     * An empty object is allowed. Each key must be a valid plugin slug and each
     * value a non-empty constraint string; a JSON array (integer keys) is
     * rejected because it cannot express slug-to-constraint pairs.
     *
     * @param  mixed  $map  Raw manifest value.
     * @param  string  $field  Field name, used in error messages.
     * @param  string  $ownSlug  The declaring plugin's own slug, rejected as a self-reference.
     *
     * @throws PluginValidationException If the map is malformed.
     */
    protected function validateConstraintMapManifestField( mixed $map, string $field, string $ownSlug ): void
    {
        if ( ! is_array( $map ) ) {
            throw PluginValidationException::invalidManifest( "Invalid {$field}. Must be an object mapping plugin slugs to version constraints." );
        }

        foreach ( $map as $slug => $constraint ) {
            if ( ! is_string( $slug ) || ! $this->validateSlug( $slug ) ) {
                throw PluginValidationException::invalidManifest( "Invalid {$field}. Each key must be a valid plugin slug." );
            }
            // A plugin cannot require or conflict with itself. The resolver
            // would otherwise report the plugin as its own inactive dependency,
            // an activation that can never succeed.
            if ( $slug === $ownSlug ) {
                throw PluginValidationException::invalidManifest( "Invalid {$field}. A plugin cannot reference its own slug ('{$ownSlug}')." );
            }
            if ( ! is_string( $constraint ) || '' === trim( $constraint ) ) {
                throw PluginValidationException::invalidManifest( "Invalid {$field}['{$slug}']. Version constraint must be a non-empty string." );
            }
        }
    }

    /**
     * Validate the optional `update` manifest key, which declares the source
     * `UpdateManager` resolves plugin updates from.
     *
     * The rules themselves live in `HasManifestParsing` so plugins and themes
     * cannot drift on a key they deliberately spell identically; this wrapper
     * only turns the shared failure reason into a plugin-namespaced exception.
     *
     * @param  mixed  $update  Raw `update` value from the manifest.
     *
     * @throws PluginValidationException If the key is malformed.
     */
    protected function validateUpdateSourceManifestField( mixed $update ): void
    {
        $reason = $this->checkUpdateSourceManifestField( $update );

        if ( null !== $reason ) {
            throw PluginValidationException::invalidManifest( $reason );
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

        // Check MIME type using finfo (mime_content_type is deprecated)
        $finfo        = finfo_open( FILEINFO_MIME_TYPE );
        $mimeType     = finfo_file( $finfo, $zipPath );
        finfo_close( $finfo );
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

        // Get the first directory name (plugin slug). The first entry must live
        // under a top-level directory so a slug can be derived; reject a ZIP
        // whose first entry is a bare file.
        $firstEntry = $zip->getNameIndex( 0 );
        if ( false === $firstEntry || ! str_contains( $firstEntry, '/' ) ) {
            $zip->close();
            throw PluginInstallationException::extractionFailed( 'unknown' );
        }

        $slug = explode( '/', $firstEntry )[0];

        // Run the derived slug through the same format check every other slug
        // entry point uses, so a crafted directory name cannot reach the
        // filesystem.
        if ( ! $this->validateSlug( $slug ) ) {
            $zip->close();
            throw PluginInstallationException::extractionFailed( $slug );
        }

        $extractPath     = $this->getPluginsPath();
        $realExtractPath = realpath( $extractPath );

        if ( false === $realExtractPath ) {
            $zip->close();
            throw PluginInstallationException::extractionFailed( $slug );
        }

        // Zip-slip guard: reject absolute/`..` entries and any entry outside
        // the derived slug directory. Without the slug check a plugin ZIP
        // carrying a sibling top-level folder could overwrite a different,
        // already-trusted plugin's files inside the plugins root.
        if ( null !== ExtensionArchive::firstUnsafeEntry( $zip, $realExtractPath, $slug ) ) {
            $zip->close();
            throw PluginInstallationException::extractionFailed( $slug );
        }

        // Zip-bomb guard: cap the uncompressed size before extracting, since
        // the passed compressed-size check says nothing about what it expands
        // to.
        $maxUncompressed = (int) config( 'cms.plugins.maxUncompressedSize', 100 * 1024 * 1024 );
        if ( ExtensionArchive::uncompressedSize( $zip ) > $maxUncompressed ) {
            $zip->close();
            throw PluginInstallationException::extractionFailed( $slug );
        }

        if ( ! $zip->extractTo( $extractPath ) ) {
            $zip->close();
            throw PluginInstallationException::extractionFailed( $slug );
        }

        $zip->close();

        return $slug;
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
     * Clear Laravel's route/config/view caches so stale plugin registrations
     * don't linger after activation state changes (#182).
     */
    protected function clearFrameworkCaches(): void
    {
        foreach ( ['route:clear', 'config:clear', 'view:clear'] as $command ) {
            try {
                Artisan::call( $command );
            } catch ( Throwable $e ) {
                logger()->warning( "Framework cache clear failed for {$command}", [
                    'exception' => $e->getMessage(),
                ] );
            }
        }
    }

    /**
     * Assert that a plugin's declared dependencies and conflicts are satisfied
     * before activation (#45).
     *
     * Conflicts take precedence: a conflicting plugin is a harder stop than a
     * merely-missing dependency, and its dedicated exception carries the
     * offending versions the generic result cannot express as cleanly.
     *
     * @since 2.9.0
     *
     * @throws PluginConflictException When an installed plugin matches a declared conflict.
     * @throws DependencyNotSatisfiedException When a dependency is missing, inactive, or version-mismatched.
     */
    protected function assertDependenciesSatisfied( Plugin $plugin ): void
    {
        $result = $this->checkDependencies( $plugin->slug );

        if ( ! empty( $result->conflicts ) ) {
            throw PluginConflictException::forConflicts( $plugin->slug, $result->conflicts );
        }

        if ( ! $result->isSatisfied() ) {
            throw DependencyNotSatisfiedException::forResult( $plugin->slug, $result );
        }
    }

    /**
     * Active plugins that declare a requirement on the given slug.
     *
     * @since 2.9.0
     *
     * @param  string  $slug  The depended-upon plugin.
     * @param  array<string,array<string,mixed>>|null  $graph  Pre-built graph to reuse, or null to build one.
     *
     * @return array<int,string>
     */
    protected function activeDependents( string $slug, ?array $graph = null ): array
    {
        $graph ??= $this->buildDependencyGraph();

        return array_values( array_filter(
            $this->dependencyResolver()->dependents( $slug, $graph ),
            static fn ( string $dependent ): bool => ! empty( $graph[ $dependent ]['is_active'] ),
        ) );
    }

    /**
     * Build a graph entry from a plugin's on-disk manifest for a plugin that
     * exists on disk but has no database row yet.
     *
     * @since 2.9.0
     *
     * @param  string  $slug  Plugin slug.
     *
     * @return array<string,mixed>|null Graph entry, or null when no manifest is found.
     */
    protected function manifestGraphEntry( string $slug ): ?array
    {
        $plugin = $this->getPlugin( $slug );
        if ( null === $plugin ) {
            return null;
        }

        $model = new Plugin( ['meta' => $plugin['manifest']] );

        return [
            'version'       => ( string ) ( $plugin['version'] ?? '0.0.0' ),
            'is_active'     => ( bool ) ( $plugin['is_active'] ?? false ),
            'requires'      => $model->required_plugins,
            'requires_host' => $model->required_host_version,
            'conflicts'     => $model->conflicting_plugins,
        ];
    }

    /**
     * Resolve a stateless dependency resolver instance.
     *
     * @since 2.9.0
     */
    protected function dependencyResolver(): DependencyResolver
    {
        return new DependencyResolver;
    }

    /**
     * Assert that the framework version installed in the host satisfies the
     * plugin's declared minimum host version (#183).
     *
     * @throws IncompatiblePluginException When the host is older than required.
     */
    protected function assertHostVersionCompatible( Plugin $plugin ): void
    {
        $required = $plugin->min_host_version;
        if ( null === $required ) {
            return;
        }

        $hostVersion = $this->resolveHostFrameworkVersion();
        if ( null === $hostVersion ) {
            // Unknown host version (e.g. path-repo dev checkout). Log so it's
            // visible, then be permissive — the framework itself is running, so
            // outright refusing every plugin here would be worse than
            // best-effort activation.
            logger()->warning( "Skipping min_host_version check for plugin '{$plugin->slug}': host framework version unresolved." );

            return;
        }

        $normalizedHost = $this->normalizeSemver( $hostVersion );
        if ( null === $normalizedHost ) {
            // Same permissive policy for parseable-but-non-semver host versions
            // (dev-main, 2.4.x-dev). Consistent with the null-host branch above.
            logger()->warning( "Skipping min_host_version check for plugin '{$plugin->slug}': host version '{$hostVersion}' is not a comparable semver." );

            return;
        }

        if ( version_compare( $normalizedHost, $required, '<' ) ) {
            throw IncompatiblePluginException::forVersion( $plugin->slug, $required, $hostVersion );
        }
    }

    /**
     * Resolve the framework's installed package version.
     *
     * Prefers the framework's own composer.json when it declares a clean
     * semver — that's the authoritative source and works uniformly for both
     * real Composer installs and path-repo / symlinked dev checkouts (where
     * InstalledVersions might report a non-comparable branch alias like
     * `dev-release/2.4`). Falls back to Composer's InstalledVersions if
     * composer.json is missing or lacks an explicit version field.
     */
    protected function resolveHostFrameworkVersion(): ?string
    {
        $composerVersion = $this->readFrameworkComposerVersion();
        if ( null !== $composerVersion && 1 === preg_match( '/^\d+\.\d+\.\d+/', $composerVersion ) ) {
            return $composerVersion;
        }

        try {
            $version = InstalledVersions::getVersion( 'artisanpack-ui/cms-framework' );
            if ( null !== $version && '' !== $version ) {
                return $version;
            }
        } catch ( Throwable $e ) {
            // Fall through.
        }

        return $composerVersion;
    }

    /**
     * Read the framework's own composer.json (relative to this file) and
     * return its declared `version` if present.
     */
    protected function readFrameworkComposerVersion(): ?string
    {
        $composerPath = __DIR__ . '/../../../../composer.json';
        if ( ! File::exists( $composerPath ) ) {
            return null;
        }

        $decoded = json_decode( ( string ) File::get( $composerPath ), true );
        if ( ! is_array( $decoded ) || empty( $decoded['version'] ) || ! is_string( $decoded['version'] ) ) {
            return null;
        }

        return $decoded['version'];
    }

    /**
     * Strip Composer version suffixes (e.g. `v` prefix, `+metadata`) down to a
     * comparable semver string. Returns null for anything that isn't parseable
     * so the caller can apply a consistent unknown-version policy instead of
     * silently coercing to 0.0.0 (which would false-fail every plugin against
     * a dev-* checkout).
     */
    protected function normalizeSemver( string $version ): ?string
    {
        $stripped = ltrim( $version, 'v' );
        $stripped = preg_replace( '/[-+].*$/', '', $stripped ) ?? $stripped;

        if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $stripped ) ) {
            return null;
        }

        return $stripped;
    }

    /**
     * Seed permission slugs declared by the plugin manifest.
     *
     * Uses artisanpack-ui/rbac's Permission model when available; falls back to
     * a no-op so tests and hosts without RBAC installed still succeed.
     */
    protected function seedPermissions( Plugin $plugin ): void
    {
        $permissions = $plugin->declared_permissions;
        if ( empty( $permissions ) ) {
            return;
        }

        $model = $this->rbacPermissionModel();
        if ( null === $model ) {
            return;
        }

        foreach ( $permissions as $slug ) {
            try {
                $model::firstOrCreate( ['slug' => $slug], ['name' => $slug] );
            } catch ( Throwable $e ) {
                logger()->warning( "Failed seeding permission '{$slug}' for plugin {$plugin->slug}", [
                    'exception' => $e->getMessage(),
                ] );
            }
        }
    }

    /**
     * Remove plugin-declared permissions on deletion.
     */
    protected function removeSeededPermissions( Plugin $plugin ): void
    {
        $permissions = $plugin->declared_permissions;
        if ( empty( $permissions ) ) {
            return;
        }

        $model = $this->rbacPermissionModel();
        if ( null === $model ) {
            return;
        }

        try {
            $model::whereIn( 'slug', $permissions )->delete();
        } catch ( Throwable $e ) {
            logger()->warning( "Failed removing permissions for plugin {$plugin->slug}", [
                'exception' => $e->getMessage(),
            ] );
        }
    }

    /**
     * Best-effort locator for artisanpack-ui/rbac's Permission model.
     *
     * @return class-string|null
     */
    protected function rbacPermissionModel(): ?string
    {
        $candidate = 'ArtisanPackUI\\RBAC\\Models\\Permission';

        return class_exists( $candidate ) ? $candidate : null;
    }

    /**
     * Roll back a plugin's migrations. Used on deletion (#182) when the plugin
     * has opted into `rollback_migrations_on_delete`.
     */
    protected function rollbackMigrations( string $slug, string $migrationsPath ): void
    {
        $safePath = $this->resolveSecureMigrationsPath( $slug, $migrationsPath );
        if ( null === $safePath ) {
            return;
        }

        Artisan::call( 'migrate:rollback', [
            '--path'  => str_replace( base_path(), '', $safePath ),
            '--force' => true,
        ] );
    }

    /**
     * Best-effort teardown when a partial activation blew up (#182).
     *
     * @param  array<string,array<int,string>>  $priorPsr4  Snapshot of the PSR-4
     *                                                      map for the plugin's
     *                                                      namespaces BEFORE
     *                                                      registerAutoloader ran.
     */
    protected function rollbackFailedActivation( Plugin $plugin, array $priorPsr4, bool $migrationsAttempted ): void
    {
        if ( $migrationsAttempted && isset( $plugin->meta['migrations_path'] ) ) {
            try {
                $this->rollbackMigrations( $plugin->slug, $plugin->meta['migrations_path'] );
            } catch ( Throwable $e ) {
                logger()->warning( "Rollback of migrations after failed activation of {$plugin->slug}", [
                    'exception' => $e->getMessage(),
                ] );
            }
        }

        if ( ! empty( $priorPsr4 ) ) {
            // Restore the PSR-4 mapping snapshot rather than wiping shared
            // namespaces to []. setPsr4 replaces (not appends) so restoring the
            // prior path list preserves other plugins that shared the prefix.
            foreach ( $priorPsr4 as $namespace => $paths ) {
                try {
                    $this->classLoader->setPsr4( $namespace, $paths );
                } catch ( Throwable $e ) {
                    logger()->warning( "Failed restoring prior PSR-4 mapping for '{$namespace}' after failed activation of {$plugin->slug}", [
                        'exception' => $e->getMessage(),
                    ] );
                }
            }
        }

        // Ensure the plugin isn't stuck marked active.
        try {
            $plugin->is_active = false;
            $plugin->save();
        } catch ( Throwable $e ) {
            // Transaction was rolled back; nothing to persist.
        }

        $this->clearCaches();
    }

    /**
     * Capture the current PSR-4 path list for each namespace the plugin
     * declares, so a failed activation can restore prior mappings without
     * blowing away paths owned by other plugins/packages that share a prefix.
     *
     * @param  array<string,string>  $psr4  namespace => relative path
     *
     * @return array<string,array<int,string>>
     */
    protected function snapshotPsr4( array $psr4 ): array
    {
        $prior       = [];
        $currentMap  = $this->classLoader->getPrefixesPsr4();
        foreach ( array_keys( $psr4 ) as $namespace ) {
            $prior[ $namespace ] = $currentMap[ $namespace ] ?? [];
        }

        return $prior;
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
