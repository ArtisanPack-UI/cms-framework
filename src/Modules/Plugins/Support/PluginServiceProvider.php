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
     * Register a Blade or Inertia admin page.
     *
     * A `view` is wrapped in a closure before it reaches the route: Laravel's
     * `Route::get( $uri, $action )` accepts a closure, a controller class, a
     * `Class@method` string or an array — a Blade view name is none of those,
     * and passing one through verbatim throws `Invalid route action`.
     *
     * A `component` is a Module Federation identifier that only the host's
     * federation runtime can resolve, so it is still passed through as-is for
     * the host to interpret.
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

        $options = array_filter( [
            'action'     => $this->resolveAdminPageAction( $config ),
            'capability' => $config['capability'] ?? 'access_admin_dashboard',
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
     *   runtime can resolve, returned as a mount-point element the host front
     *   end hydrates.
     * - neither → a 501, so the operator sees a clear "not configured" response
     *   instead of a white-screened application.
     *
     * @since 2.8.0
     *
     * @param  array{view?:string,component?:string}  $config
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
            return static fn (): Response => response( sprintf(
                '<div data-cms-federated-module="%s"></div>',
                e( $component ),
            ) );
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

        $normalized = [
            'title'      => $entry['label'],
            'slug'       => $slug,
            'parent'     => $entry['parent'] ?? null,
            'section'    => null,
            'icon'       => $entry['icon'] ?? ( $entry['iconId'] ?? '' ),
            'iconId'     => $entry['iconId'] ?? ( $entry['icon'] ?? '' ),
            'capability' => $entry['permission'] ?? '',
            'permission' => $entry['permission'] ?? '',
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
     * @param  array<int,string>  $exposes  Optional list of exposed module names.
     */
    protected function registerFederatedModule( string $name, string $entryPath, array $exposes = [] ): void
    {
        $descriptor = [
            'entry' => $entryPath,
        ];

        if ( ! empty( $exposes ) ) {
            $descriptor['exposes'] = array_values( $exposes );
        }

        $this->pluginRegistry()->setFederatedModule( $name, $descriptor );
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
