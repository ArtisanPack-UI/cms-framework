<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Support;

use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminMenuManager;
use ArtisanPackUI\CMSFramework\Modules\Admin\Support\NavUrl;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use RuntimeException;

/**
 * Base service provider for cms-framework plugins.
 *
 * Extending this class gives plugin authors a consistent, opinionated API for
 * the most common registration patterns: admin pages, nav entries, federated
 * frontend modules, and manifest-aware path/config helpers.
 *
 * Extending is optional — plain Laravel ServiceProviders continue to work — but
 * `PluginManager` uses the presence of this base to expose richer metadata to
 * host apps.
 *
 * @since 2.4.0
 */
abstract class PluginServiceProvider extends ServiceProvider
{
    /**
     * Parsed plugin.json contents for the plugin backing this provider.
     *
     * @var array<string,mixed>|null
     */
    protected ?array $manifest = null;

    /**
     * Bridge the plugin manifest's declarative `nav_entries` and
     * `federated_module` into the runtime {@see PluginRegistry}.
     *
     * `plugin.json`'s `nav_entries` and `federated_module` are validated by
     * {@see \ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager}
     * but were otherwise inert: only an imperative {@see registerNavEntry()} /
     * {@see registerFederatedModule()} call ever populated the registry, forcing
     * authors to declare the same data twice. This bridges the declaration so
     * listing the fields in the manifest is enough (#324).
     *
     * The framework calls this once per plugin, right after the provider is
     * registered. Imperative calls always win: a manifest entry is only bridged
     * when its slug (nav) or module name is not already in the registry, so a
     * provider may override or extend any declarative entry from boot()
     * regardless of registration ordering.
     *
     * @since 2.10.0
     */
    public function bridgeManifestRegistrations(): void
    {
        $manifest = $this->loadManifest();
        $registry = $this->pluginRegistry();

        $this->bridgeManifestFederatedModule( $manifest, $registry );
        $this->bridgeManifestNavEntries( $manifest, $registry );
    }

    /**
     * Register a Blade or Inertia admin page.
     *
     * A `view` is wrapped in a closure before it reaches the route: Laravel's
     * `Route::get( $uri, $action )` accepts a closure, a controller class, a
     * `Class@method` string or an array — a Blade view name is none of those,
     * and passing one through verbatim throws `Invalid route action`.
     *
     * A `component` is a Module Federation identifier that only the host's
     * federation runtime can resolve. The framework wraps it in a closure that
     * renders the `cms::admin.layouts.federated` mount-point shell by default;
     * a federation host overrides that through the
     * `ap.cmsFramework.admin.federatedPageAction` filter. See
     * {@see resolveAdminPageAction()}.
     *
     * @param  string  $slug  Route/URL slug for the admin page.
     * @param  array{title?:string,section?:string,view?:string,component?:string,capability?:string,icon?:string,order?:int}  $config
     */
    protected function registerAdminPage( string $slug, array $config ): void
    {
        /** @var AdminMenuManager $menu */
        $menu = $this->app->make( AdminMenuManager::class );

        $title   = $config['title'] ?? ucfirst( $slug );
        $section = $config['section'] ?? null;

        // Coerce a null OR empty capability to the admin-dashboard baseline: the
        // `??` alone lets an explicit `'capability' => ''` through, which would
        // register an `auth`-only route any authenticated user could reach.
        $capability = $config['capability'] ?? '';
        $capability = '' !== $capability ? $capability : 'access_admin_dashboard';

        $options = array_filter( [
            'action'     => $this->resolveAdminPageAction( $config ),
            'capability' => $capability,
            'icon'       => $config['icon'] ?? 'fas.puzzle-piece',
            'order'      => $config['order'] ?? 50,
            'menuTitle'  => $config['menuTitle'] ?? $title,
        ], fn ( $value ) => null !== $value );

        $menu->addPage( $title, $slug, $section, $options );
    }

    /**
     * Turn an admin-page config into something `Route::get()` will accept.
     *
     * Always returns a closure, never a bare string. Admin routes are
     * registered from a `booted()` callback, so an action `Route::get()`
     * rejects — a bare component identifier, or an empty string when the page
     * declares neither a `view` nor a `component` — would throw during route
     * registration and take *every* request down, not just the misconfigured
     * page. Wrapping each outcome in a closure keeps the failure on the one
     * route:
     *
     * - `view` → renders the Blade view, with the route's own parameters passed
     *   through as view data so a page at `reports/{id}` can read `$id`.
     * - `component` → a Module Federation identifier only the host's federation
     *   runtime can resolve. The framework owns no frontend and ships no
     *   federation runtime, so the host must hydrate the component. The shipped
     *   default renders the framework-owned `cms::admin.layouts.federated`
     *   shell, which emits a mount-point element inside the admin chrome — plus
     *   a visible fallback notice so an unhydrated page is never a silent blank
     *   div. A host hydrates it either by scanning for the mount attribute in
     *   its front end, or by overriding the default through the
     *   `ap.cmsFramework.admin.federatedPageAction` filter, which receives the
     *   default action, the component identifier, and the page config and
     *   returns its own route action. Inertia-based hosts ( e.g. Keystone CMS )
     *   take the filter path to bridge to their `plugins/<remote>/<page>`
     *   runtime. See `docs/plugin-authoring.md`.
     * - neither → a 501, so the operator sees a clear "not configured" response
     *   instead of a white-screened application.
     *
     * @since 2.8.0
     *
     * @param  array{title?:string,view?:string,component?:string}  $config
     *
     * @return Closure The route action.
     */
    protected function resolveAdminPageAction( array $config ): Closure
    {
        $view = isset( $config['view'] ) ? ( string ) $config['view'] : '';

        if ( '' !== $view ) {
            return static fn ( Request $request ): View => view( $view, $request->route()?->parameters() ?? [] );
        }

        $component = isset( $config['component'] ) ? ( string ) $config['component'] : '';

        if ( '' !== $component ) {
            $title = isset( $config['title'] ) ? ( string ) $config['title'] : null;

            $default = static fn (): View => view( 'cms::admin.layouts.federated', [
                'component' => $component,
                'title'     => $title,
            ] );

            $action = applyFilters(
                'ap.cmsFramework.admin.federatedPageAction',
                $default,
                $component,
                $config,
            );

            return $action instanceof Closure ? $action : $default;
        }

        return static fn (): Response => response(
            __( 'This admin page is not configured: it declares neither a view nor a component.' ),
            501,
        );
    }

    /**
     * Inject a nav entry into the admin menu.
     *
     * The entry is stored in the container-bound PluginRegistry so a single
     * framework-owned filter callback surfaces all plugin nav entries in one
     * place. Overwriting by slug is idempotent — repeated calls from the same
     * plugin (e.g. under Octane) don't accumulate.
     *
     * @param  array{slug:string,label:string,url?:string,icon?:string,iconId?:string,permission?:string,order?:int,external?:bool,parent?:string}  $entry
     */
    protected function registerNavEntry( array $entry ): void
    {
        if ( empty( $entry['slug'] ) || empty( $entry['label'] ) ) {
            throw new RuntimeException( 'registerNavEntry requires slug and label.' );
        }

        $slug = ( string ) $entry['slug'];
        $url  = $this->normalizeNavUrl( $entry['url'] ?? '#' );

        // A nav entry with no declared permission falls back to the admin
        // dashboard baseline rather than rendering to everyone who can see the
        // menu — the same fallback registerAdminPage() applies to an empty
        // capability, so a manifest-bridged entry cannot surface an ungated link.
        $permission = '' !== trim( (string) ( $entry['permission'] ?? '' ) )
            ? (string) $entry['permission']
            : 'access_admin_dashboard';

        $normalized = [
            'title'      => $entry['label'],
            'slug'       => $slug,
            'parent'     => $entry['parent'] ?? null,
            'section'    => null,
            'icon'       => $entry['icon'] ?? ( $entry['iconId'] ?? '' ),
            'iconId'     => $entry['iconId'] ?? ( $entry['icon'] ?? '' ),
            'capability' => $permission,
            'permission' => $permission,
            'order'      => ( int ) ( $entry['order'] ?? 50 ),
            'route'      => $url,
            'menuTitle'  => $entry['label'],
            'label'      => $entry['label'],
            'url'        => $url,
            'external'   => ( bool ) ( $entry['external'] ?? false ),
        ];

        $this->pluginRegistry()->setNavEntry( $slug, $normalized );
    }

    /**
     * Register a Module Federation entry so host apps that support runtime-loaded
     * React pages can discover and mount plugin frontend modules.
     *
     * `$entryPath` is an absolute `http(s)` URL or a root-relative URL such as
     * `/build/remoteEntry.js`, both used as-is, or a path relative to the
     * plugin directory such as `dist/assets/remoteEntry.js`.
     * A relative entry's directory is published through the framework's
     * plugin asset route and the descriptor carries the absolute URL
     * `/plugins/{slug}/assets/remoteEntry.js`, so the plugin needs no asset
     * controller of its own. The relative entry must sit in a subdirectory
     * of the plugin, and a plugin can publish only one asset directory. An
     * entry that breaks either rule is logged and not registered.
     *
     * @since 2.14.0 Added `$bootModule` and relative-entry resolution.
     *
     * @param  string  $name  The federation remote name.
     * @param  string  $entryPath  Absolute or root-relative URL, or path relative to the plugin directory.
     * @param  array<int,string>  $exposes  Optional list of exposed module names.
     * @param  string|null  $bootModule  Optional exposed module the host preloads before the first page mounts ( e.g. `./boot` ).
     */
    protected function registerFederatedModule( string $name, string $entryPath, array $exposes = [], ?string $bootModule = null ): void
    {
        $entry = $this->resolveFederatedEntry( $name, $entryPath );

        if ( null === $entry ) {
            return;
        }

        $descriptor = [
            'entry' => $entry,
        ];

        $exposes = array_values( array_filter( $exposes, static fn ( $module ): bool => is_string( $module ) && '' !== $module ) );

        if ( ! empty( $exposes ) ) {
            $descriptor['exposes'] = $exposes;
        }

        if ( null !== $bootModule && '' !== $bootModule ) {
            $descriptor['bootModule'] = $bootModule;
        }

        $this->pluginRegistry()->setFederatedModule( $name, $descriptor );
    }

    /**
     * Turn a federated module entry into the absolute URL hosts load.
     *
     * An absolute `http(s)` URL or a root-relative URL passes through. A
     * servable relative path publishes its directory on the plugin asset route
     * and resolves to that route's URL. Anything else is logged and returns null, as does a second
     * asset directory for the same plugin, since the route serves one
     * directory per plugin.
     *
     * @since 2.14.0
     *
     * @param  string  $name  The federation remote name, for log context.
     * @param  string  $entryPath  The entry as declared.
     *
     * @return string|null The absolute entry URL, or null when it cannot be served.
     */
    protected function resolveFederatedEntry( string $name, string $entryPath ): ?string
    {
        // A root-relative URL ( `/build/remoteEntry.js` ) is the host's own
        // path, which a provider could always register before 2.14.0, so it
        // passes through unchanged. Manifest validation still refuses it.
        if ( FederatedModuleEntry::isAbsoluteUrl( $entryPath )
            || 1 === preg_match( '#^/[^/\\\\]#', $entryPath ) ) {
            return $entryPath;
        }

        $context = [
            'provider' => static::class,
            'module'   => $name,
            'entry'    => $entryPath,
        ];

        if ( ! FederatedModuleEntry::isServableRelativePath( $entryPath ) ) {
            logger()->warning( 'Federated module not registered: its entry must be an absolute http(s) URL, a root-relative URL, or a path inside a subdirectory of the plugin ( e.g. dist/remoteEntry.js ).', $context );

            return null;
        }

        try {
            $slug = $this->pluginSlug();
        } catch ( RuntimeException ) {
            logger()->warning( 'Federated module not registered: a relative entry needs the plugin slug from plugin.json to build its asset URL.', $context );

            return null;
        }

        $directory = $this->pluginPath( dirname( $entryPath ) );
        $file      = basename( $entryPath );
        $registry  = $this->pluginRegistry();
        $existing  = $registry->assetDirectory( $slug );

        if ( null !== $existing && $existing['directory'] !== $directory ) {
            logger()->warning( 'Federated module not registered: the plugin already publishes a different asset directory, and only one is served per plugin.', $context + [
                'published' => $existing['directory'],
            ] );

            return null;
        }

        $registry->setAssetDirectory( $slug, $directory, $file );

        // Built with url() rather than route(): plugins register while the
        // framework is still booting, before the `plugins.assets` route loads.
        // The file name is left unencoded: isServableRelativePath() limited it
        // to the route's URL-safe character set, and encoding `@` as `%40`
        // would only obscure the URL.
        return url( 'plugins/' . rawurlencode( $slug ) . '/assets/' . $file );
    }

    /**
     * Bridge the manifest's singular `federated_module` object.
     *
     * Re-checks the shape the manifest validator enforces so a descriptor that
     * reached a non-validating code path can never corrupt the registry. The
     * descriptor is keyed by an optional `name`, falling back to the plugin
     * slug — the singular field models one federated module per plugin. An
     * already-registered name is left untouched so imperative registrations win.
     *
     * The manifest's snake_case `boot_module` is passed through as the
     * descriptor's `bootModule`, and a relative `entry` is resolved to the
     * plugin asset route's URL by {@see registerFederatedModule()}.
     *
     * @since 2.10.0
     * @since 2.14.0 Bridges `boot_module` and resolves relative entries.
     *
     * @param  array<string,mixed>  $manifest
     */
    protected function bridgeManifestFederatedModule( array $manifest, PluginRegistry $registry ): void
    {
        $module = $manifest['federated_module'] ?? null;

        if ( ! is_array( $module ) || empty( $module['entry'] ) || ! is_string( $module['entry'] ) ) {
            return;
        }

        $name = ( isset( $module['name'] ) && is_string( $module['name'] ) && '' !== trim( $module['name'] ) )
            ? ( string ) $module['name']
            : ( isset( $manifest['slug'] ) && is_string( $manifest['slug'] ) ? ( string ) $manifest['slug'] : '' );

        if ( '' === $name || array_key_exists( $name, $registry->federatedModules() ) ) {
            return;
        }

        $exposes = ( isset( $module['exposes'] ) && is_array( $module['exposes'] ) )
            ? array_values( array_filter( $module['exposes'], 'is_string' ) )
            : [];

        $bootModule = ( isset( $module['boot_module'] ) && is_string( $module['boot_module'] ) )
            ? $module['boot_module']
            : null;

        $this->registerFederatedModule( $name, $module['entry'], $exposes, $bootModule );
    }

    /**
     * Bridge the manifest's `nav_entries` list.
     *
     * Skips entries missing the slug/label the validator requires, and any slug
     * already present in the registry, so imperative registrations win. Each
     * surviving entry passes through {@see registerNavEntry()}, which applies
     * the same URL sanitization and normalization as a programmatic call.
     *
     * @since 2.10.0
     *
     * @param  array<string,mixed>  $manifest
     */
    protected function bridgeManifestNavEntries( array $manifest, PluginRegistry $registry ): void
    {
        $entries = $manifest['nav_entries'] ?? null;

        if ( ! is_array( $entries ) ) {
            return;
        }

        foreach ( $entries as $entry ) {
            if ( ! is_array( $entry ) || empty( $entry['slug'] ) || empty( $entry['label'] ) ) {
                continue;
            }

            if ( array_key_exists( ( string ) $entry['slug'], $registry->navEntries() ) ) {
                continue;
            }

            $this->registerNavEntry( $entry );
        }
    }

    /**
     * Resolve a path relative to the plugin's installation directory.
     */
    protected function pluginPath( ?string $subpath = null ): string
    {
        $root = base_path( config( 'cms.plugins.directory', 'plugins' ) . '/' . $this->pluginSlug() );

        if ( null === $subpath ) {
            return $root;
        }

        return $root . '/' . ltrim( $subpath, '/' );
    }

    /**
     * Read the plugin's manifest (or a specific key via dot notation).
     */
    protected function pluginConfig( ?string $key = null ): mixed
    {
        $manifest = $this->loadManifest();

        if ( null === $key ) {
            return $manifest;
        }

        $value = $manifest;
        foreach ( explode( '.', $key ) as $segment ) {
            if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
                return null;
            }
            $value = $value[ $segment ];
        }

        return $value;
    }

    /**
     * Slug used to locate the plugin directory. Defaults to the manifest's
     * `slug`; plugins may override for edge cases (multi-plugin repos, etc).
     */
    protected function pluginSlug(): string
    {
        $manifest = $this->loadManifest();

        if ( empty( $manifest['slug'] ) ) {
            throw new RuntimeException( 'Unable to determine plugin slug: manifest is missing a slug.' );
        }

        return ( string ) $manifest['slug'];
    }

    /**
     * Load and cache the plugin's manifest. Walks up from the provider file
     * looking for a plugin.json — but ONLY accepts a match whose directory is
     * a direct child of the configured plugins directory. This prevents a
     * malicious plugin from adopting an unrelated manifest (host root, vendor
     * subdir, or another plugin) by placing its provider deep in a vendor
     * tree.
     *
     * @return array<string,mixed>
     */
    protected function loadManifest(): array
    {
        if ( null !== $this->manifest ) {
            return $this->manifest;
        }

        $reflection    = new ReflectionClass( static::class );
        $file          = ( string ) $reflection->getFileName();
        $pluginsRoot   = realpath( base_path( config( 'cms.plugins.directory', 'plugins' ) ) );
        $realProvider  = realpath( $file );

        if ( false === $pluginsRoot || false === $realProvider ) {
            return $this->manifest = [];
        }

        // Provider must live under the plugins directory. Reject anything else
        // (host root, vendor, other packages) outright.
        if ( ! str_starts_with( $realProvider . DIRECTORY_SEPARATOR, $pluginsRoot . DIRECTORY_SEPARATOR ) ) {
            return $this->manifest = [];
        }

        // Walk from the provider's dir up to the plugins root (bounded by the
        // real directory hierarchy, not a magic depth). The plugin's own root
        // is a direct child of pluginsRoot — that's the ONLY manifest we
        // trust.
        $dir = dirname( $realProvider );
        while ( $dir !== $pluginsRoot && str_starts_with( $dir . DIRECTORY_SEPARATOR, $pluginsRoot . DIRECTORY_SEPARATOR ) ) {
            $parent = dirname( $dir );
            if ( $parent === $pluginsRoot ) {
                // $dir is the plugin's own root directory.
                $candidate = $dir . '/plugin.json';
                if ( File::exists( $candidate ) ) {
                    $decoded = json_decode( ( string ) File::get( $candidate ), true );
                    if ( is_array( $decoded ) ) {
                        return $this->manifest = $decoded;
                    }
                }
                break;
            }
            if ( $parent === $dir ) {
                break;
            }
            $dir = $parent;
        }

        return $this->manifest = [];
    }

    /**
     * Resolve the shared PluginRegistry singleton, binding a fresh one if the
     * host container hasn't done so yet (defensive — the framework's
     * PluginsServiceProvider is the canonical binder).
     */
    protected function pluginRegistry(): PluginRegistry
    {
        if ( ! $this->app->bound( PluginRegistry::class ) ) {
            $this->app->singleton( PluginRegistry::class );
        }

        return $this->app->make( PluginRegistry::class );
    }

    /**
     * Sanitize a plugin-supplied URL before it enters the nav registry.
     * Rejects unsafe schemes; falls back to '#' for anything not obviously a
     * navigation target. AdminMenuManager also sanitizes on render, but
     * blocking at the ingress point keeps the registry itself clean — and the
     * registry is a public surface in its own right, read by
     * `PluginRegistry::navEntries()` and `Plugin::getNavEntriesAttribute()`
     * rather than only through `getAdminMenu()`.
     *
     * Shares {@see NavUrl} with the render path: these rules were duplicated
     * until 2.8.0, which is how the render copy came to be hardened against
     * `java\tscript:` while this one kept storing the raw value.
     *
     * Takes `mixed` rather than `string`: the entry is a plugin-supplied array
     * and this file declares `strict_types=1`, so a `url` that is anything but
     * a string — an `HtmlString`, an int, an accidental array — raised a
     * TypeError at the parameter boundary. That happens inside the plugin's
     * `boot()`, which is the same whole-application failure mode as the route
     * action bug this release fixes. Non-strings now take the documented '#'
     * fallback instead.
     *
     * @param  mixed  $url  The plugin-supplied URL.
     *
     * @return string A URL safe to store and render.
     */
    protected function normalizeNavUrl( mixed $url ): string
    {
        return NavUrl::sanitizeValue( $url, static::class );
    }
}
