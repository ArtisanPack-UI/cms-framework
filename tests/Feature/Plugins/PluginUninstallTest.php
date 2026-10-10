<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Composer\Autoload\ClassLoader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

beforeEach( function (): void {
    $this->manager     = app( PluginManager::class );
    $this->pluginsPath = base_path( 'plugins' );
    $this->pluginPath  = $this->pluginsPath . '/plugin-with-uninstaller';

    File::ensureDirectoryExists( $this->pluginsPath );
    File::copyDirectory( __DIR__ . '/../../Support/Plugins/plugin-with-uninstaller', $this->pluginPath );

    $this->manifest = json_decode( File::get( $this->pluginPath . '/plugin.json' ), true );

    $this->createPlugin = function ( array $overrides = [], bool $isActive = false ): Plugin {
        $manifest = array_merge( $this->manifest, $overrides );

        return Plugin::create( [
            'slug'      => $manifest['slug'],
            'name'      => $manifest['name'],
            'version'   => $manifest['version'],
            'is_active' => $isActive,
            'meta'      => $manifest,
        ] );
    };

    $this->uninstallCalls = [];
    addAction( 'test.pluginWithUninstaller.uninstalled', function ( string $slug, bool $filesPresent ): void {
        $this->uninstallCalls[] = [ 'slug' => $slug, 'filesPresent' => $filesPresent ];
    } );
} );

afterEach( function (): void {
    if ( File::exists( $this->pluginPath ) ) {
        File::deleteDirectory( $this->pluginPath );
    }
} );

it( 'invokes the uninstall class when deleting an inactive plugin', function (): void {
    ( $this->createPlugin )();

    $this->manager->delete( 'plugin-with-uninstaller' );

    expect( $this->uninstallCalls )->toBe( [
        [ 'slug' => 'plugin-with-uninstaller', 'filesPresent' => true ],
    ] )
        ->and( Plugin::where( 'slug', 'plugin-with-uninstaller' )->exists() )->toBeFalse()
        ->and( File::exists( $this->pluginPath ) )->toBeFalse();
} );

it( 'invokes the uninstall class when deleting an active plugin', function (): void {
    ( $this->createPlugin )( [], true );

    $this->manager->delete( 'plugin-with-uninstaller' );

    expect( $this->uninstallCalls )->toHaveCount( 1 );
} );

it( 'invokes the uninstall class when plugin files are kept', function (): void {
    ( $this->createPlugin )();

    $this->manager->delete( 'plugin-with-uninstaller', false );

    expect( $this->uninstallCalls )->toHaveCount( 1 )
        ->and( File::exists( $this->pluginPath ) )->toBeTrue();
} );

it( 'runs the uninstall class after the deleting hook and before the deleted hook', function (): void {
    ( $this->createPlugin )();

    $order = [];
    addAction( 'ap.cmsFramework.plugin.deleting', function () use ( &$order ): void {
        $order[] = 'deleting';
    } );
    addAction( 'test.pluginWithUninstaller.uninstalled', function () use ( &$order ): void {
        $order[] = 'uninstall';
    } );
    addAction( 'ap.cmsFramework.plugin.deleted', function () use ( &$order ): void {
        $order[] = 'deleted';
    } );

    $this->manager->delete( 'plugin-with-uninstaller' );

    expect( $order )->toBe( [ 'deleting', 'uninstall', 'deleted' ] );
} );

it( 'logs and still deletes the plugin when the uninstall class throws', function (): void {
    ( $this->createPlugin )( [ 'uninstall' => 'PluginWithUninstaller\\ThrowingUninstall' ] );

    Log::shouldReceive( 'error' )
        ->once()
        ->withArgs( fn ( string $message, array $context ): bool => str_contains( $message, 'plugin-with-uninstaller' )
            && 'Uninstall exploded.' === $context['exception'] );

    expect( $this->manager->delete( 'plugin-with-uninstaller' ) )->toBeTrue()
        ->and( Plugin::where( 'slug', 'plugin-with-uninstaller' )->exists() )->toBeFalse()
        ->and( File::exists( $this->pluginPath ) )->toBeFalse();
} );

it( 'logs and still deletes the plugin when the uninstall class is missing or not invokable', function ( string $class, string $reason ): void {
    ( $this->createPlugin )( [ 'uninstall' => $class ] );

    Log::shouldReceive( 'error' )
        ->once()
        ->withArgs( fn ( string $message, array $context ): bool => str_contains( $context['exception'], $reason ) );

    expect( $this->manager->delete( 'plugin-with-uninstaller' ) )->toBeTrue()
        ->and( Plugin::where( 'slug', 'plugin-with-uninstaller' )->exists() )->toBeFalse();
} )->with( [
    'missing class'      => [ 'PluginWithUninstaller\\DoesNotExist', 'not found' ],
    'not invokable'      => [ 'PluginWithUninstaller\\NotInvokable', 'not invokable' ],
] );

it( 'skips an uninstall class outside the plugin namespace on a legacy row', function (): void {
    ( $this->createPlugin )( [ 'uninstall' => 'Illuminate\\Support\\Str' ] );

    Log::shouldReceive( 'warning' )
        ->once()
        ->withArgs( fn ( string $message ): bool => str_contains( $message, 'outside its autoload.psr-4 namespaces' ) );

    expect( $this->manager->delete( 'plugin-with-uninstaller' ) )->toBeTrue()
        ->and( $this->uninstallCalls )->toBe( [] );
} );

it( 'refuses an uninstall class that resolves outside the plugin directory', function ( array $autoload, string $class ): void {
    ( $this->createPlugin )( [ 'autoload' => $autoload, 'uninstall' => $class ] );

    Log::shouldReceive( 'error' )
        ->once()
        ->withArgs( fn ( string $message, array $context ): bool => str_contains( $context['exception'], 'not defined inside the plugin directory' ) );

    expect( $this->manager->delete( 'plugin-with-uninstaller' ) )->toBeTrue()
        ->and( $this->uninstallCalls )->toBe( [] );
} )->with( [
    'namespace overlapping a host class' => [
        [ 'psr-4' => [ 'Illuminate\\Support\\' => 'src/' ] ],
        'Illuminate\\Support\\Str',
    ],
] );

it( 'refuses an uninstall class loaded through a psr-4 path that climbs out of the plugin', function (): void {
    $outsideDir = $this->pluginsPath . '/uninstall-traversal-target';
    File::ensureDirectoryExists( $outsideDir );
    File::put( $outsideDir . '/Uninstall.php', <<<'PHP'
        <?php

        namespace TraversalPlugin;

        doAction( 'test.pluginWithUninstaller.uninstalled', 'included', true );

        class Uninstall
        {
            public function __invoke(): void
            {
                doAction( 'test.pluginWithUninstaller.uninstalled', 'traversal', true );
            }
        }
        PHP );

    ( $this->createPlugin )( [
        'autoload'  => [ 'psr-4' => [ 'TraversalPlugin\\' => '../uninstall-traversal-target/' ] ],
        'uninstall' => 'TraversalPlugin\\Uninstall',
    ] );

    Log::shouldReceive( 'error' )
        ->once()
        ->withArgs( fn ( string $message, array $context ): bool => str_contains( $context['exception'], 'not defined inside the plugin directory' ) );

    try {
        expect( $this->manager->delete( 'plugin-with-uninstaller' ) )->toBeTrue()
            ->and( $this->uninstallCalls )->toBe( [] );
    } finally {
        File::deleteDirectory( $outsideDir );
    }
} );

it( 'does nothing extra for a plugin without an uninstall class', function (): void {
    $manifest = $this->manifest;
    unset( $manifest['uninstall'] );

    Plugin::create( [
        'slug'      => $manifest['slug'],
        'name'      => $manifest['name'],
        'version'   => $manifest['version'],
        'is_active' => false,
        'meta'      => $manifest,
    ] );

    expect( $this->manager->delete( 'plugin-with-uninstaller' ) )->toBeTrue()
        ->and( $this->uninstallCalls )->toBe( [] );
} );

it( 'restores the PSR-4 map after running an inactive plugin\'s uninstall class', function (): void {
    ( $this->createPlugin )();

    $classLoader = collect( spl_autoload_functions() )
        ->first( fn ( mixed $autoloader ): bool => is_array( $autoloader ) && $autoloader[0] instanceof ClassLoader )[0];

    $this->manager->delete( 'plugin-with-uninstaller' );

    expect( $this->uninstallCalls )->toHaveCount( 1 )
        ->and( $classLoader->getPrefixesPsr4()['PluginWithUninstaller\\'] ?? [] )->toBe( [] );
} );
