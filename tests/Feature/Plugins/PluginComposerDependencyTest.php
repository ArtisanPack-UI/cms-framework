<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Contracts\ComposerPackageInstallerInterface;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\ComposerDependencyNotSatisfiedException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use ArtisanPackUI\CMSFramework\Tests\Support\Composer\FakeComposerPackageInstaller;
use Composer\Autoload\ClassLoader;
use Illuminate\Support\Facades\File;

beforeEach( function (): void {
    $this->installer = new FakeComposerPackageInstaller;
    app()->instance( ComposerPackageInstallerInterface::class, $this->installer );

    $this->manager = app( PluginManager::class );
} );

/**
 * Persist a plugin declaring a `composer` block.
 */
function makeComposerPlugin( string $slug, array $composer ): Plugin
{
    return Plugin::create( [
        'slug'      => $slug,
        'name'      => ucfirst( $slug ),
        'version'   => '1.0.0',
        'is_active' => false,
        'meta'      => [ 'slug' => $slug, 'composer' => $composer ],
    ] );
}

/**
 * The Composer ClassLoader the running process (and PluginManager) uses.
 */
function activeComposerClassLoader(): ClassLoader
{
    foreach ( spl_autoload_functions() as $autoloader ) {
        if ( is_array( $autoloader ) && $autoloader[0] instanceof ClassLoader ) {
            return $autoloader[0];
        }
    }

    throw new RuntimeException( 'Composer ClassLoader not found.' );
}

describe( 'Composer-package activation gate', function (): void {
    it( 'activates without touching the installer when a plugin declares no composer block', function (): void {
        Plugin::create( [
            'slug'      => 'plain',
            'name'      => 'Plain',
            'version'   => '1.0.0',
            'is_active' => false,
            'meta'      => [ 'slug' => 'plain' ],
        ] );

        expect( $this->manager->activate( 'plain' ) )->toBeTrue()
            ->and( $this->installer->installCalls )->toBe( [] );
    } );

    it( 'activates when the required package is already installed and in range', function (): void {
        $this->installer->installed = [ 'artisanpack-ui/convertkit' => '1.4.0' ];
        makeComposerPlugin( 'convertkit', [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        expect( $this->manager->activate( 'convertkit' ) )->toBeTrue()
            ->and( $this->installer->installCalls )->toBe( [] );

        expect( Plugin::where( 'slug', 'convertkit' )->first()->is_active )->toBeTrue();
    } );

    it( 'installs a missing package on activation when auto-install is enabled', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->installsToApply = [ 'artisanpack-ui/convertkit' => '1.4.0' ];
        makeComposerPlugin( 'convertkit', [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        expect( $this->manager->activate( 'convertkit' ) )->toBeTrue()
            ->and( $this->installer->installCalls )->toHaveCount( 1 )
            ->and( $this->installer->installCalls[0]['constraints'] )
            ->toBe( [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        expect( Plugin::where( 'slug', 'convertkit' )->first()->is_active )->toBeTrue();
    } );

    it( 'fails closed when a persisted requirement no longer passes validation', function ( array $composer ): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );

        // makeComposerPlugin seats meta directly, bypassing validateManifest — so
        // this models a requirement that reached the row unvalidated. Activation
        // must re-validate it and fail closed before touching the installer.
        makeComposerPlugin( 'tampered', $composer );

        try {
            $this->manager->activate( 'tampered' );
            $this->fail( 'Expected ComposerDependencyNotSatisfiedException.' );
        } catch ( ComposerDependencyNotSatisfiedException $e ) {
            expect( $e->getMessage() )->toContain( 'invalid Composer requirement' );
        }

        expect( $this->installer->installCalls )->toBe( [] )
            ->and( Plugin::where( 'slug', 'tampered' )->first()->is_active )->toBeFalse();
    } )->with( [
        'unbounded constraint'    => [ [ 'acme/pkg' => '*' ] ],
        'lower-bound-only'        => [ [ 'acme/pkg' => '>=1.0' ] ],
        'dev branch'              => [ [ 'acme/pkg' => 'dev-main' ] ],
        'invalid package name'    => [ [ 'not-a-package' => '^1.0' ] ],
    ] );

    it( 'fails closed without installing when auto-install is disabled', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', false );
        makeComposerPlugin( 'convertkit', [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        try {
            $this->manager->activate( 'convertkit' );
            $this->fail( 'Expected ComposerDependencyNotSatisfiedException.' );
        } catch ( ComposerDependencyNotSatisfiedException $e ) {
            expect( $e->pluginSlug )->toBe( 'convertkit' )
                ->and( $e->result?->missing )->toBe( [
                    [ 'package' => 'artisanpack-ui/convertkit', 'required' => '^1.2' ],
                ] );
        }

        expect( $this->installer->installCalls )->toBe( [] )
            ->and( Plugin::where( 'slug', 'convertkit' )->first()->is_active )->toBeFalse();
    } );

    it( 'fails closed and does not activate when the install cannot complete', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->failInstall = true;
        $this->installer->failReason  = 'Could not reach Packagist.';
        makeComposerPlugin( 'convertkit', [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        try {
            $this->manager->activate( 'convertkit' );
            $this->fail( 'Expected ComposerDependencyNotSatisfiedException.' );
        } catch ( ComposerDependencyNotSatisfiedException $e ) {
            expect( $e->pluginSlug )->toBe( 'convertkit' )
                ->and( $e->getMessage() )->toContain( 'Could not reach Packagist.' );
        }

        expect( Plugin::where( 'slug', 'convertkit' )->first()->is_active )->toBeFalse();
    } );

    it( 'fails closed when a version mismatch survives the install attempt', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        // Installed but out of range, and the simulated install brings nothing
        // new — so the post-install re-resolve still reports a mismatch.
        $this->installer->installed = [ 'artisanpack-ui/convertkit' => '0.9.0' ];
        makeComposerPlugin( 'convertkit', [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        try {
            $this->manager->activate( 'convertkit' );
            $this->fail( 'Expected ComposerDependencyNotSatisfiedException.' );
        } catch ( ComposerDependencyNotSatisfiedException $e ) {
            expect( $e->result?->versionMismatch )->toBe( [
                [ 'package' => 'artisanpack-ui/convertkit', 'required' => '^1.2', 'installed' => '0.9.0' ],
            ] );
        }

        expect( Plugin::where( 'slug', 'convertkit' )->first()->is_active )->toBeFalse();
    } );

    it( 'installs every unmet package in a multi-package composer block', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->installed        = [ 'acme/already' => '2.1.0' ];
        $this->installer->installsToApply  = [ 'acme/one' => '1.0.0', 'acme/two' => '3.2.0' ];
        makeComposerPlugin( 'bundle', [
            'acme/one'     => '^1.0',
            'acme/two'     => '^3.0',
            'acme/already' => '^2.0',
        ] );

        expect( $this->manager->activate( 'bundle' ) )->toBeTrue()
            ->and( $this->installer->installCalls )->toHaveCount( 1 );

        // Only the two unmet packages are handed to the installer; the satisfied
        // one is not re-required.
        expect( $this->installer->installCalls[0]['constraints'] )->toBe( [
            'acme/one' => '^1.0',
            'acme/two' => '^3.0',
        ] );
    } );

    it( 'seats the regenerated autoload maps on the class loader after installing', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );

        $packageDir = sys_get_temp_dir() . '/composer-dep-' . uniqid();
        File::ensureDirectoryExists( $packageDir . '/src' );

        // The install brings the package into range, and the fake reports the
        // regenerated maps Composer would have written — PSR-4 (multi-path),
        // a classmap entry, and an eager files helper.
        $this->installer->installsToApply = [ 'acme/widget' => '1.4.0' ];
        $this->installer->autoloadMaps    = [
            'psr-4'    => [ 'Acme\\Widget\\' => [ $packageDir . '/src', $packageDir . '/lib' ] ],
            'classmap' => [ 'Acme\\Widget\\Legacy' => $packageDir . '/src/Legacy.php' ],
            'files'    => [],
        ];
        makeComposerPlugin( 'widget', [ 'acme/widget' => '^1.2' ] );

        try {
            expect( $this->manager->activate( 'widget' ) )->toBeTrue();

            $loader = activeComposerClassLoader();

            expect( $loader->getPrefixesPsr4() )->toHaveKey( 'Acme\\Widget\\' )
                ->and( $loader->getPrefixesPsr4()['Acme\\Widget\\'] )->toContain( $packageDir . '/src' )
                ->and( $loader->getPrefixesPsr4()['Acme\\Widget\\'] )->toContain( $packageDir . '/lib' )
                ->and( $loader->getClassMap() )->toHaveKey( 'Acme\\Widget\\Legacy' );
        } finally {
            File::deleteDirectory( $packageDir );
        }
    } );

    it( 'does not seat autoload maps when the requirement is already satisfied', function (): void {
        $this->installer->installed    = [ 'acme/widget' => '1.4.0' ];
        $this->installer->autoloadMaps = [
            'psr-4' => [ 'Acme\\Unused\\' => [ '/tmp/should-not-register' ] ],
        ];
        makeComposerPlugin( 'widget', [ 'acme/widget' => '^1.2' ] );

        expect( $this->manager->activate( 'widget' ) )->toBeTrue()
            ->and( activeComposerClassLoader()->getPrefixesPsr4() )->not->toHaveKey( 'Acme\\Unused\\' );
    } );

    it( 'refuses — without installing — a package the host itself requires', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );

        // Sandbox base_path() to a root composer.json that requires acme/rootdep,
        // so the guard can see the host's own requirement.
        $target = sys_get_temp_dir() . '/cms-host-' . uniqid();
        File::ensureDirectoryExists( $target );
        File::put( $target . '/composer.json', json_encode( [
            'require' => [ 'acme/rootdep' => '^1.0' ],
        ] ) );

        $originalBase = base_path();
        app()->setBasePath( $target );

        try {
            // Case-insensitive: the plugin names it in mixed case.
            makeComposerPlugin( 'shop', [ 'Acme/RootDep' => '^2.0' ] );

            try {
                $this->manager->activate( 'shop' );
                $this->fail( 'Expected ComposerDependencyNotSatisfiedException.' );
            } catch ( ComposerDependencyNotSatisfiedException $e ) {
                expect( $e->getMessage() )->toContain( 'the host already requires' )
                    ->and( $e->getMessage() )->toContain( 'acme/rootdep' );
            }

            expect( $this->installer->installCalls )->toBe( [] )
                ->and( Plugin::where( 'slug', 'shop' )->first()->is_active )->toBeFalse();
        } finally {
            app()->setBasePath( $originalBase );
            File::deleteDirectory( $target );
        }
    } );

    it( 'runs unscoped migrations after installing packages that ship their own (#338)', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->installsToApply = [ 'artisanpack-ui/convertkit' => '1.4.0' ];
        makeComposerPlugin( 'convertkit', [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        // Probe subclass records whether the post-activation unscoped migrate
        // fired, and its slug, so we can assert both firing and ordering
        // (packages installed → migrate) without touching Composer, the
        // filesystem, or Artisan.
        $installer = $this->installer;
        $manager   = new class ( $installer ) extends PluginManager {
            public int $packageMigrateCalls = 0;

            public ?string $packageMigrateSlug = null;

            public function __construct( private FakeComposerPackageInstaller $bound )
            {
                parent::__construct();
            }

            protected function composerPackageInstaller(): ComposerPackageInstallerInterface
            {
                return $this->bound;
            }

            protected function runComposerPackageMigrations( Plugin $plugin ): void
            {
                $this->packageMigrateCalls++;
                $this->packageMigrateSlug = $plugin->slug;
            }
        };

        expect( $manager->activate( 'convertkit' ) )->toBeTrue()
            ->and( $manager->packageMigrateCalls )->toBe( 1 )
            ->and( $manager->packageMigrateSlug )->toBe( 'convertkit' );
    } );

    it( 'skips the unscoped migrate when composer requirements are already satisfied', function (): void {
        // No auto-install needed; the requirement is already in range in the
        // vendor tree, so no packages are freshly installed this activation.
        $this->installer->installed = [ 'artisanpack-ui/convertkit' => '1.4.0' ];
        makeComposerPlugin( 'convertkit', [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        $installer = $this->installer;
        $manager   = new class ( $installer ) extends PluginManager {
            public int $packageMigrateCalls = 0;

            public function __construct( private FakeComposerPackageInstaller $bound )
            {
                parent::__construct();
            }

            protected function composerPackageInstaller(): ComposerPackageInstallerInterface
            {
                return $this->bound;
            }

            protected function runComposerPackageMigrations( Plugin $plugin ): void
            {
                $this->packageMigrateCalls++;
            }
        };

        expect( $manager->activate( 'convertkit' ) )->toBeTrue()
            ->and( $manager->packageMigrateCalls )->toBe( 0 );
    } );

    it( 'skips the unscoped migrate when the plugin declares no composer block', function (): void {
        Plugin::create( [
            'slug'      => 'plain',
            'name'      => 'Plain',
            'version'   => '1.0.0',
            'is_active' => false,
            'meta'      => [ 'slug' => 'plain' ],
        ] );

        $installer = $this->installer;
        $manager   = new class ( $installer ) extends PluginManager {
            public int $packageMigrateCalls = 0;

            public function __construct( private FakeComposerPackageInstaller $bound )
            {
                parent::__construct();
            }

            protected function composerPackageInstaller(): ComposerPackageInstallerInterface
            {
                return $this->bound;
            }

            protected function runComposerPackageMigrations( Plugin $plugin ): void
            {
                $this->packageMigrateCalls++;
            }
        };

        expect( $manager->activate( 'plain' ) )->toBeTrue()
            ->and( $manager->packageMigrateCalls )->toBe( 0 );
    } );

    it( 'rolls back activation when the post-install unscoped migrate throws (#338)', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->installsToApply = [ 'artisanpack-ui/convertkit' => '1.4.0' ];
        makeComposerPlugin( 'convertkit', [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        $installer = $this->installer;
        $manager   = new class ( $installer ) extends PluginManager {
            public function __construct( private FakeComposerPackageInstaller $bound )
            {
                parent::__construct();
            }

            protected function composerPackageInstaller(): ComposerPackageInstallerInterface
            {
                return $this->bound;
            }

            protected function runComposerPackageMigrations( Plugin $plugin ): void
            {
                throw new RuntimeException( 'simulated migration failure' );
            }
        };

        try {
            $manager->activate( 'convertkit' );
            $this->fail( 'Expected RuntimeException.' );
        } catch ( RuntimeException $e ) {
            expect( $e->getMessage() )->toBe( 'simulated migration failure' );
        }

        expect( Plugin::where( 'slug', 'convertkit' )->first()->is_active )->toBeFalse();
    } );

    it( 'fails closed with a retry hint when a successful install stays invisible in-process', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );

        // install() succeeds but applies nothing — modelling the OPcache-restricted
        // host where the regenerated metadata is not visible this request.
        $this->installer->installsToApply = [];
        makeComposerPlugin( 'convertkit', [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        try {
            $this->manager->activate( 'convertkit' );
            $this->fail( 'Expected ComposerDependencyNotSatisfiedException.' );
        } catch ( ComposerDependencyNotSatisfiedException $e ) {
            expect( $e->getMessage() )->toContain( 'Retry the activation' );
        }

        expect( $this->installer->installCalls )->toHaveCount( 1 )
            ->and( Plugin::where( 'slug', 'convertkit' )->first()->is_active )->toBeFalse();
    } );
} );
