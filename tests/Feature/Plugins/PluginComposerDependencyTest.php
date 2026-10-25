<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Contracts\ComposerPackageInstallerInterface;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\ComposerDependencyNotSatisfiedException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginInstallationException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use ArtisanPackUI\CMSFramework\Tests\Support\Composer\FakeComposerPackageInstaller;
use Composer\Autoload\ClassLoader;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

beforeEach( function (): void {
    // A fresh install migrates in a subprocess (#431); never spawn a real one.
    Process::fake();

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

    it( 'migrates in a fresh process after installing packages that ship their own (#338, #431)', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->installsToApply = [ 'artisanpack-ui/convertkit' => '1.4.0' ];
        makeComposerPlugin( 'convertkit', [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        // Probe subclass records which package migrate fired, and its slug, so
        // we can assert a fresh install takes the fresh-process path (#431)
        // without touching Composer, the filesystem, or Artisan.
        $installer = $this->installer;
        $manager   = new class ( $installer ) extends PluginManager {
            public int $packageMigrateCalls = 0;

            public int $freshProcessMigrateCalls = 0;

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
            }

            protected function runComposerPackageMigrationsInFreshProcess( Plugin $plugin ): void
            {
                $this->freshProcessMigrateCalls++;
                $this->packageMigrateSlug = $plugin->slug;
            }
        };

        expect( $manager->activate( 'convertkit' ) )->toBeTrue()
            ->and( $manager->freshProcessMigrateCalls )->toBe( 1 )
            ->and( $manager->packageMigrateCalls )->toBe( 0 )
            ->and( $manager->packageMigrateSlug )->toBe( 'convertkit' );
    } );

    it( 'runs the fresh-process migrate as php artisan migrate --force in the host root (#431)', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->installsToApply = [ 'artisanpack-ui/convertkit' => '1.4.0' ];
        makeComposerPlugin( 'convertkit', [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        expect( $this->manager->activate( 'convertkit' ) )->toBeTrue();

        Process::assertRanTimes( function ( PendingProcess $process ): bool {
            $command = ( array ) $process->command;

            return base_path() === $process->path
                && [ 'artisan', 'migrate', '--force', '--no-interaction', '--no-ansi' ] === array_slice( $command, 1 )
                && is_string( $command[0] ) && '' !== $command[0];
        }, 1 );
    } );

    it( 'does not spawn a migrate process when the packages were already installed', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->installed = [ 'artisanpack-ui/convertkit' => '1.4.0' ];
        makeComposerPlugin( 'convertkit', [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        expect( $this->manager->activate( 'convertkit' ) )->toBeTrue();

        Process::assertNothingRan();
    } );

    it( 'creates the tables of a package whose provider only registers in a freshly booted process (#431)', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->installsToApply = [ 'artisanpack-ui/convertkit' => '1.4.0' ];
        makeComposerPlugin( 'convertkit', [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        $migrationsPath = sys_get_temp_dir() . '/cms-431-' . uniqid() . '/migrations';
        File::ensureDirectoryExists( $migrationsPath );
        File::put( $migrationsPath . '/2026_01_01_000000_create_convertkit_feeds_table.php', <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration {
                public function up(): void
                {
                    Schema::create( 'convertkit_feeds', function ( Blueprint $table ): void {
                        $table->id();
                    } );
                }
            };
            PHP );

        // Stand-in for the package's own provider: registers its migrations
        // through `loadMigrationsFrom()`, as `artisanpack-ui/convertkit` does.
        $packageProvider = new class ( app(), $migrationsPath ) extends ServiceProvider {
            public function __construct( $app, private string $migrationsPath )
            {
                parent::__construct( $app );
            }

            public function boot(): void
            {
                $this->loadMigrationsFrom( $this->migrationsPath );
            }
        };

        // The fake subprocess boots the way a real one would: from the
        // regenerated package manifest, so the package provider is registered
        // before `migrate` runs. The activating process never registers it.
        $packageProviderRegisteredInProcess = null;
        Process::fake( function () use ( $packageProvider, &$packageProviderRegisteredInProcess ) {
            $packageProviderRegisteredInProcess = null !== app()->getProvider( $packageProvider::class );

            app()->register( $packageProvider );
            Artisan::call( 'migrate', [ '--force' => true ] );

            return Process::result();
        } );

        try {
            expect( Schema::hasTable( 'convertkit_feeds' ) )->toBeFalse();

            expect( $this->manager->activate( 'convertkit' ) )->toBeTrue()
                ->and( $packageProviderRegisteredInProcess )->toBeFalse()
                ->and( Schema::hasTable( 'convertkit_feeds' ) )->toBeTrue();
        } finally {
            File::deleteDirectory( dirname( $migrationsPath ) );
        }
    } );

    it( 'rolls back activation and surfaces the output when the fresh-process migrate fails (#431)', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->installsToApply = [ 'artisanpack-ui/convertkit' => '1.4.0' ];
        makeComposerPlugin( 'convertkit', [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        Process::fake( [ '*' => Process::result( errorOutput: "\e[31mSQLSTATE[42S01]: table already exists\e[0m", exitCode: 1 ) ] );

        try {
            $this->manager->activate( 'convertkit' );
            $this->fail( 'Expected RuntimeException.' );
        } catch ( RuntimeException $e ) {
            expect( $e->getMessage() )
                ->toContain( "plugin 'convertkit'" )
                ->toContain( 'SQLSTATE[42S01]: table already exists' )
                ->not->toContain( "\e" )
                ->not->toContain( '[31m' );
        }

        expect( Plugin::where( 'slug', 'convertkit' )->first()->is_active )->toBeFalse();
    } );

    it( 'rolls back activation when the fresh-process migrate cannot run at all (#431)', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->installsToApply = [ 'artisanpack-ui/convertkit' => '1.4.0' ];
        makeComposerPlugin( 'convertkit', [ 'artisanpack-ui/convertkit' => '^1.2' ] );

        Process::fake( function (): void {
            throw new RuntimeException( 'The process exceeded the timeout of 600 seconds.' );
        } );

        try {
            $this->manager->activate( 'convertkit' );
            $this->fail( 'Expected RuntimeException.' );
        } catch ( RuntimeException $e ) {
            expect( $e->getMessage() )
                ->toContain( "plugin 'convertkit'" )
                ->toContain( 'exceeded the timeout' )
                ->and( $e->getPrevious() )->toBeInstanceOf( RuntimeException::class );
        }

        expect( Plugin::where( 'slug', 'convertkit' )->first()->is_active )->toBeFalse();
    } );

    it( 'skips the unscoped migrate when composer requirements are already satisfied and auto-install is off', function (): void {
        // No auto-install needed; the requirement is already in range in the
        // vendor tree, so no packages are freshly installed this activation.
        // With auto-install off this keeps the pre-2.16 rule (#430).
        config()->set( 'cms.plugins.autoInstallComposerDependencies', false );
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

    it( 'rolls back activation when the in-process package migrate throws (#338)', function (): void {
        // Already installed, so the migrate runs in-process (#430, #431).
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->installed = [ 'artisanpack-ui/convertkit' => '1.4.0' ];
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

describe( 'Install-time Composer packages (#430)', function (): void {
    beforeEach( function (): void {
        $this->pluginsPath = base_path( 'plugins' );
        $this->zipPaths    = [];

        File::ensureDirectoryExists( $this->pluginsPath );

        // Discovery is cached by default; disable so installFromDisk sees a
        // directory written mid-test.
        config()->set( 'cms.plugins.cacheEnabled', false );

        $this->manifest = [
            'slug'     => 'ck-install',
            'name'     => 'ConvertKit Install',
            'version'  => '1.0.0',
            'composer' => [ 'artisanpack-ui/convertkit' => '^1.2' ],
        ];

        $this->buildZip = function (): string {
            $zipPath = storage_path( 'app/composer-install-' . uniqid() . '.zip' );
            File::ensureDirectoryExists( dirname( $zipPath ) );

            $zip = new ZipArchive;
            $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE );
            $zip->addFromString( 'ck-install/plugin.json', json_encode( $this->manifest ) );
            $zip->close();

            $this->zipPaths[] = $zipPath;

            return $zipPath;
        };
    } );

    afterEach( function (): void {
        File::deleteDirectory( $this->pluginsPath . '/ck-install' );

        foreach ( $this->zipPaths as $zipPath ) {
            File::delete( $zipPath );
        }
    } );

    it( 'installs unmet packages when a plugin is installed from a ZIP', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->installsToApply = [ 'artisanpack-ui/convertkit' => '1.4.0' ];

        $plugin = $this->manager->installFromZip( ( $this->buildZip )() );

        expect( $plugin->slug )->toBe( 'ck-install' )
            ->and( $this->installer->installCalls )->toBe( [
                [ 'slug' => 'ck-install', 'constraints' => [ 'artisanpack-ui/convertkit' => '^1.2' ] ],
            ] )
            ->and( Plugin::where( 'slug', 'ck-install' )->exists() )->toBeTrue();

        // The packages are now satisfied, so activation installs nothing more.
        expect( $this->manager->activate( 'ck-install' ) )->toBeTrue()
            ->and( $this->installer->installCalls )->toHaveCount( 1 );
    } );

    it( 'removes the extracted plugin and reports the Composer reason when a ZIP install cannot install its packages', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->failInstall = true;
        $this->installer->failReason  = 'Could not reach Packagist.';

        $events = [];
        addAction( 'ap.cmsFramework.plugin.installing', function ( string $slug ) use ( &$events ): void {
            $events[] = $slug;
        } );

        try {
            $this->manager->installFromZip( ( $this->buildZip )() );
            $this->fail( 'Expected PluginInstallationException.' );
        } catch ( PluginInstallationException $e ) {
            expect( $e->getMessage() )->toBe( "Plugin 'ck-install' could not install its required Composer packages: Could not reach Packagist." )
                ->and( $e->getPrevious() )->toBeInstanceOf( ComposerDependencyNotSatisfiedException::class );
        }

        expect( File::exists( $this->pluginsPath . '/ck-install' ) )->toBeFalse()
            ->and( Plugin::where( 'slug', 'ck-install' )->exists() )->toBeFalse()
            ->and( $events )->toBe( [] );
    } );

    it( 'removes the extracted plugin and rethrows when seating the installed packages fails unexpectedly', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->installsToApply     = [ 'artisanpack-ui/convertkit' => '1.4.0' ];
        $this->installer->autoloadMapsFailure = new RuntimeException( 'Autoload maps are unreadable.' );

        expect( fn () => $this->manager->installFromZip( ( $this->buildZip )() ) )
            ->toThrow( RuntimeException::class, 'Autoload maps are unreadable.' );

        expect( File::exists( $this->pluginsPath . '/ck-install' ) )->toBeFalse()
            ->and( Plugin::where( 'slug', 'ck-install' )->exists() )->toBeFalse();
    } );

    it( 'leaves an on-disk plugin in place when installFromDisk cannot install its packages', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->failInstall = true;
        $this->installer->failReason  = 'Could not reach Packagist.';

        File::ensureDirectoryExists( $this->pluginsPath . '/ck-install' );
        File::put( $this->pluginsPath . '/ck-install/plugin.json', json_encode( $this->manifest ) );

        expect( fn () => $this->manager->installFromDisk( 'ck-install' ) )
            ->toThrow( PluginInstallationException::class, 'Could not reach Packagist.' );

        expect( File::exists( $this->pluginsPath . '/ck-install/plugin.json' ) )->toBeTrue()
            ->and( Plugin::where( 'slug', 'ck-install' )->exists() )->toBeFalse();
    } );

    it( 'leaves the composer block alone at install and fails closed at activation when auto-install is off', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', false );
        $this->installer->installsToApply = [ 'artisanpack-ui/convertkit' => '1.4.0' ];

        $this->manager->installFromZip( ( $this->buildZip )() );

        expect( $this->installer->installCalls )->toBe( [] )
            ->and( Plugin::where( 'slug', 'ck-install' )->exists() )->toBeTrue();

        expect( fn () => $this->manager->activate( 'ck-install' ) )
            ->toThrow( ComposerDependencyNotSatisfiedException::class );

        expect( $this->installer->installCalls )->toBe( [] )
            ->and( Plugin::where( 'slug', 'ck-install' )->first()->is_active )->toBeFalse();
    } );

    it( 'runs package migrations when activating a plugin whose packages were installed at install time', function (): void {
        config()->set( 'cms.plugins.autoInstallComposerDependencies', true );
        $this->installer->installsToApply = [ 'artisanpack-ui/convertkit' => '1.4.0' ];

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

        $manager->installFromZip( ( $this->buildZip )() );

        expect( $manager->packageMigrateCalls )->toBe( 0 );

        // A later request: the packages are already satisfied, but their
        // migrations still have to run once the plugin's provider boots.
        expect( $manager->activate( 'ck-install' ) )->toBeTrue()
            ->and( $manager->packageMigrateCalls )->toBe( 1 );
    } );
} );
