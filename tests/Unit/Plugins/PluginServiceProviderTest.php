<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminMenuManager;
use ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminPageManager;
use ArtisanPackUI\CMSFramework\Modules\Admin\Support\NavUrl;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\PluginRegistry;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\PluginServiceProvider;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

beforeEach( function (): void {
    Gate::before( fn ( ?Illuminate\Contracts\Auth\Authenticatable $user, string $ability ) => true );

    removeAllFilters( 'ap.cmsFramework.admin.menu' );
    removeAllFilters( 'ap.plugins.federatedModules' );
    removeAllFilters( 'ap.cmsFramework.admin.federatedPageAction' );

    $this->app->singleton( AdminMenuManager::class, fn () => new AdminMenuManager );
    $this->app->singleton( PluginRegistry::class );

    // Wire the same single-callback filter the framework's PluginsServiceProvider
    // sets up, so registry contents surface in the admin menu.
    $registry = app( PluginRegistry::class );
    addFilter( 'ap.cmsFramework.admin.menu', function ( array $menu ) use ( $registry ): array {
        foreach ( $registry->navEntries() as $slug => $entry ) {
            $menu[ $slug ] = array_merge( $entry, $menu[ $slug ] ?? [] );
        }

        return $menu;
    } );
    addFilter( 'ap.plugins.federatedModules', function ( array $modules ) use ( $registry ): array {
        return array_merge( $modules, $registry->federatedModules() );
    } );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.cmsFramework.admin.menu' );
    removeAllFilters( 'ap.plugins.federatedModules' );
    removeAllFilters( 'ap.cmsFramework.admin.federatedPageAction' );
} );

/**
 * Bind an AdminPageManager that records what `AdminMenuManager::addPage()`
 * hands it, so tests can assert on the route action without registering real
 * routes.
 */
function capturingPageManager(): AdminPageManager
{
    $manager = new class extends AdminPageManager {
        /** @var array<string,array{action:mixed,capability:?string}> */
        public array $captured = [];

        public function register( string $slug, mixed $action, ?string $capability ): void
        {
            $this->captured[ $slug ] = [
                'action'     => $action,
                'capability' => $capability,
            ];

            parent::register( $slug, $action, $capability );
        }
    };

    app()->instance( AdminPageManager::class, $manager );

    return $manager;
}

it( 'registers an admin page through the AdminMenuManager', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerAdminPage( 'my-plugin', [
                'title'      => 'My Plugin',
                'icon'       => 'fas.star',
                'capability' => '',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'my-plugin'];
        }
    };

    $provider->boot();

    $menu = app( AdminMenuManager::class )->getAdminMenu();

    expect( $menu )->toHaveKey( 'my-plugin' )
        ->and( $menu['my-plugin']['label'] )->toBe( 'My Plugin' );
} );

it( 'wraps a Blade view in a closure so the route action is valid', function (): void {
    $pages = capturingPageManager();

    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerAdminPage( 'my-plugin', [
                'title'      => 'My Plugin',
                'view'       => 'cms::admin.layouts.app',
                'capability' => '',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'my-plugin'];
        }
    };

    $provider->boot();

    $action = $pages->captured['my-plugin']['action'];

    // A view name handed to Route::get() throws `Invalid route action`; a
    // closure returning the rendered view is what Laravel actually accepts.
    expect( $action )->toBeInstanceOf( Closure::class );

    $rendered = $action( Request::create( '/admin/my-plugin' ) );

    expect( $rendered )->toBeInstanceOf( View::class )
        ->and( $rendered->name() )->toBe( 'cms::admin.layouts.app' );
} );

it( 'renders the federated shell for a component identifier', function (): void {
    $pages = capturingPageManager();

    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerAdminPage( 'my-plugin', [
                'title'      => 'My Plugin',
                'component'  => 'myPluginAdmin/Panel',
                'capability' => '',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'my-plugin'];
        }
    };

    $provider->boot();

    // A component is never handed to Route::get() as a bare string (which it
    // rejects, crashing route registration for the whole app). It resolves to
    // a closure rendering the framework's federated mount-point shell inside
    // the admin chrome for the host front end to hydrate.
    $action = $pages->captured['my-plugin']['action'];

    expect( $action )->toBeInstanceOf( Closure::class );

    $rendered = $action();

    expect( $rendered )->toBeInstanceOf( View::class )
        ->and( $rendered->name() )->toBe( 'cms::admin.layouts.federated' )
        ->and( $rendered->getData() )->toMatchArray( [
            'component' => 'myPluginAdmin/Panel',
            'title'     => 'My Plugin',
        ] );
} );

it( 'lets a federation host override the component action via the filter', function (): void {
    $pages = capturingPageManager();

    // A host swaps the shipped shell for its own mount strategy. The filter
    // receives the default action, the component identifier and the config.
    addFilter(
        'ap.cmsFramework.admin.federatedPageAction',
        function ( Closure $default, string $component, array $config ): Closure {
            return static fn () => 'host-mounted: ' . $component;
        },
    );

    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerAdminPage( 'my-plugin', [
                'title'      => 'My Plugin',
                'component'  => 'myPluginAdmin/Panel',
                'capability' => '',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'my-plugin'];
        }
    };

    $provider->boot();

    $action = $pages->captured['my-plugin']['action'];

    expect( $action )->toBeInstanceOf( Closure::class )
        ->and( $action() )->toBe( 'host-mounted: myPluginAdmin/Panel' );
} );

it( 'falls back to the default action when the filter returns a non-closure', function (): void {
    $pages = capturingPageManager();

    // A misbehaving subscriber returns something that is not a route action;
    // the framework must not hand it to Route::get(). It keeps the default.
    addFilter(
        'ap.cmsFramework.admin.federatedPageAction',
        fn ( Closure $default, string $component, array $config ): string => 'not-a-closure',
    );

    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerAdminPage( 'my-plugin', [
                'title'      => 'My Plugin',
                'component'  => 'myPluginAdmin/Panel',
                'capability' => '',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'my-plugin'];
        }
    };

    $provider->boot();

    $action = $pages->captured['my-plugin']['action'];

    expect( $action )->toBeInstanceOf( Closure::class )
        ->and( $action() )->toBeInstanceOf( View::class )
        ->and( $action()->name() )->toBe( 'cms::admin.layouts.federated' );
} );

it( 'returns a 501 for an admin page with neither a view nor a component', function (): void {
    $pages = capturingPageManager();

    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerAdminPage( 'broken-plugin', [
                'title'      => 'Broken',
                'capability' => '',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'broken-plugin'];
        }
    };

    $provider->boot();

    // A misconfigured page fails on its own route rather than throwing during
    // route registration and taking every request down with it.
    $response = $pages->captured['broken-plugin']['action']();

    expect( $response->getStatusCode() )->toBe( 501 );
} );

it( 'injects a nav entry via the ap.cmsFramework.admin.menu filter', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerNavEntry( [
                'slug'       => 'reports',
                'label'      => 'Reports',
                'url'        => '/admin/reports',
                'icon'       => 'fas.chart-bar',
                'permission' => 'reports.view',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'reports-plugin'];
        }
    };

    $provider->boot();

    $menu = app( AdminMenuManager::class )->getAdminMenu();

    expect( $menu )->toHaveKey( 'reports' )
        ->and( $menu['reports']['url'] )->toBe( '/admin/reports' )
        ->and( $menu['reports']['permission'] )->toBe( 'reports.view' )
        ->and( $menu['reports']['external'] )->toBeFalse();
} );

it( 'reduces a non-string nav url to # instead of raising a TypeError', function ( mixed $url ): void {
    expect( NavUrl::sanitizeValue( $url ) )->toBe( '#' );
} )->with( [
    // Htmlable without __toString: not Stringable, so it cannot be coerced.
    'non-stringable htmlable' => fn () => new class implements Htmlable {
        public function toHtml(): string
        {
            return 'javascript:alert(1)';
        }
    },
    // Stringable, but still not a string — strict_types rejected it too.
    'html string' => fn () => new HtmlString( 'javascript:alert(1)' ),
    'array'       => fn () => ['javascript:alert(1)'],
    'integer'     => fn () => 42,
] );

it( 'stores # for a non-string nav url rather than dying in the plugin boot', function (): void {
    // registerNavEntry takes a plugin-supplied array, and PluginServiceProvider
    // runs under strict_types — a non-string `url` used to TypeError at
    // normalizeNavUrl's parameter boundary inside boot(), taking the whole app
    // down rather than taking the documented fallback.
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerNavEntry( [
                'slug'  => 'docs',
                'label' => 'Docs',
                'url'   => new HtmlString( 'javascript:alert(1)' ),
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'docs-plugin'];
        }
    };

    $provider->boot();

    expect( app( PluginRegistry::class )->navEntries()['docs']['url'] )->toBe( '#' );
} );

it( 'sanitizes javascript: URLs in nav entries down to #', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerNavEntry( [
                'slug'  => 'evil',
                'label' => 'Docs',
                'url'   => 'javascript:alert(1)',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'evil-plugin'];
        }
    };

    $provider->boot();

    $menu = app( AdminMenuManager::class )->getAdminMenu();

    expect( $menu['evil']['url'] )->toBe( '#' );
} );

it( 'is idempotent when the same nav entry is registered twice', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerNavEntry( [
                'slug'  => 'reports',
                'label' => 'Reports',
                'url'   => '/admin/reports',
            ] );
            $this->registerNavEntry( [
                'slug'  => 'reports',
                'label' => 'Reports v2',
                'url'   => '/admin/reports',
            ] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'reports-plugin'];
        }
    };

    $provider->boot();

    $entries = app( PluginRegistry::class )->navEntries();

    expect( $entries )->toHaveCount( 1 )
        ->and( $entries['reports']['label'] )->toBe( 'Reports v2' );
} );

it( 'exposes federated modules via a filter', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
            $this->registerFederatedModule( 'reports', 'dist/remoteEntry.js', ['./App'] );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = ['slug' => 'reports-plugin'];
        }
    };

    $provider->boot();

    $modules = applyFilters( 'ap.plugins.federatedModules', [] );

    expect( $modules )->toHaveKey( 'reports' )
        ->and( $modules['reports']['entry'] )->toBe( 'dist/remoteEntry.js' )
        ->and( $modules['reports']['exposes'] )->toBe( ['./App'] );
} );

it( 'resolves pluginPath and pluginConfig from the manifest', function (): void {
    $provider = new class( app() ) extends PluginServiceProvider {
        public function boot(): void
        {
        }

        public function exposePluginPath( ?string $subpath = null ): string
        {
            return $this->pluginPath( $subpath );
        }

        public function exposePluginConfig( ?string $key = null ): mixed
        {
            return $this->pluginConfig( $key );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = [
                'slug'   => 'my-plugin',
                'name'   => 'My Plugin',
                'nested' => ['key' => 'value'],
            ];
        }
    };

    expect( $provider->exposePluginPath() )->toEndWith( '/plugins/my-plugin' )
        ->and( $provider->exposePluginPath( 'resources/js' ) )->toEndWith( '/plugins/my-plugin/resources/js' )
        ->and( $provider->exposePluginConfig( 'name' ) )->toBe( 'My Plugin' )
        ->and( $provider->exposePluginConfig( 'nested.key' ) )->toBe( 'value' )
        ->and( $provider->exposePluginConfig( 'missing' ) )->toBeNull();
} );

/**
 * Build a provider whose manifest is exactly $manifest and whose boot() runs
 * the given imperative callback, so the manifest-bridge tests can exercise both
 * declarative and imperative registration in one place.
 *
 * @param  array<string,mixed>  $manifest
 */
function manifestProvider( array $manifest, ?Closure $boot = null ): PluginServiceProvider
{
    return new class( app(), $manifest, $boot ) extends PluginServiceProvider {
        /** @param array<string,mixed> $pluginManifest */
        public function __construct( $app, private array $pluginManifest, private ?Closure $onBoot )
        {
            parent::__construct( $app );
        }

        public function boot(): void
        {
            if ( null !== $this->onBoot ) {
                ( $this->onBoot )( $this );
            }
        }

        public function imperativeNavEntry( array $entry ): void
        {
            $this->registerNavEntry( $entry );
        }

        public function imperativeFederatedModule( string $name, string $entry, array $exposes = [] ): void
        {
            $this->registerFederatedModule( $name, $entry, $exposes );
        }

        protected function loadManifest(): array
        {
            return $this->manifest = $this->pluginManifest;
        }
    };
}

it( 'auto-registers manifest nav_entries when declaration alone is used', function (): void {
    $provider = manifestProvider( [
        'slug'        => 'reports-plugin',
        'nav_entries' => [
            [
                'slug'       => 'reports',
                'label'      => 'Reports',
                'url'        => '/admin/reports',
                'permission' => 'reports.view',
            ],
        ],
    ] );

    $provider->bridgeManifestRegistrations();

    $entries = app( PluginRegistry::class )->navEntries();

    expect( $entries )->toHaveKey( 'reports' )
        ->and( $entries['reports']['url'] )->toBe( '/admin/reports' )
        ->and( $entries['reports']['permission'] )->toBe( 'reports.view' );
} );

it( 'auto-registers a manifest federated_module keyed by the plugin slug', function (): void {
    $provider = manifestProvider( [
        'slug'             => 'reports-plugin',
        'federated_module' => [
            'entry'   => 'dist/remoteEntry.js',
            'exposes' => ['./App', './Panel'],
        ],
    ] );

    $provider->bridgeManifestRegistrations();

    $modules = applyFilters( 'ap.plugins.federatedModules', [] );

    expect( $modules )->toHaveKey( 'reports-plugin' )
        ->and( $modules['reports-plugin']['entry'] )->toBe( 'dist/remoteEntry.js' )
        ->and( $modules['reports-plugin']['exposes'] )->toBe( ['./App', './Panel'] );
} );

it( 'keys a federated_module by its explicit name when the manifest provides one', function (): void {
    $provider = manifestProvider( [
        'slug'             => 'reports-plugin',
        'federated_module' => [
            'name'  => 'reportsAdmin',
            'entry' => 'dist/remoteEntry.js',
        ],
    ] );

    $provider->bridgeManifestRegistrations();

    $modules = applyFilters( 'ap.plugins.federatedModules', [] );

    expect( $modules )->toHaveKey( 'reportsAdmin' )
        ->and( $modules )->not->toHaveKey( 'reports-plugin' )
        ->and( $modules['reportsAdmin'] )->not->toHaveKey( 'exposes' );
} );

it( 'sanitizes a manifest nav_entry url through registerNavEntry', function (): void {
    $provider = manifestProvider( [
        'slug'        => 'evil-plugin',
        'nav_entries' => [
            [ 'slug' => 'evil', 'label' => 'Evil', 'url' => 'javascript:alert(1)' ],
        ],
    ] );

    $provider->bridgeManifestRegistrations();

    expect( app( PluginRegistry::class )->navEntries()['evil']['url'] )->toBe( '#' );
} );

it( 'ignores a malformed federated_module and invalid nav_entries', function (): void {
    $provider = manifestProvider( [
        'slug'             => 'broken-plugin',
        'federated_module' => ['exposes' => ['./App']], // no entry
        'nav_entries'      => [
            ['label' => 'No Slug'],
            ['slug' => 'no-label'],
            'not-an-array',
        ],
    ] );

    $provider->bridgeManifestRegistrations();

    expect( app( PluginRegistry::class )->navEntries() )->toBeEmpty()
        ->and( app( PluginRegistry::class )->federatedModules() )->toBeEmpty();
} );

it( 'lets an imperative nav registration override a manifest entry regardless of order', function (): void {
    // Imperative first (as if boot() ran before the bridge): the bridge must
    // not clobber it. Then bridge: the manifest slug is already present.
    $provider = manifestProvider(
        [
            'slug'        => 'reports-plugin',
            'nav_entries' => [
                ['slug' => 'reports', 'label' => 'Manifest Reports', 'url' => '/manifest'],
            ],
        ],
        fn ( $p ) => $p->imperativeNavEntry( [
            'slug'  => 'reports',
            'label' => 'Imperative Reports',
            'url'   => '/imperative',
        ] ),
    );

    $provider->boot();
    $provider->bridgeManifestRegistrations();

    $entries = app( PluginRegistry::class )->navEntries();

    expect( $entries )->toHaveCount( 1 )
        ->and( $entries['reports']['label'] )->toBe( 'Imperative Reports' )
        ->and( $entries['reports']['url'] )->toBe( '/imperative' );
} );

it( 'lets an imperative federated module override a manifest one regardless of order', function (): void {
    $provider = manifestProvider(
        [
            'slug'             => 'reports-plugin',
            'federated_module' => ['entry' => 'dist/manifest.js'],
        ],
        fn ( $p ) => $p->imperativeFederatedModule( 'reports-plugin', 'dist/imperative.js', ['./App'] ),
    );

    $provider->boot();
    $provider->bridgeManifestRegistrations();

    $modules = app( PluginRegistry::class )->federatedModules();

    expect( $modules['reports-plugin']['entry'] )->toBe( 'dist/imperative.js' )
        ->and( $modules['reports-plugin']['exposes'] )->toBe( ['./App'] );
} );
