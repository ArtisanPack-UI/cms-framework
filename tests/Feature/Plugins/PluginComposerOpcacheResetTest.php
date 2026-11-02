<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\WebOpcacheResetter;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Contracts\ComposerPackageInstallerInterface;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\ComposerDependencyNotSatisfiedException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use ArtisanPackUI\CMSFramework\Tests\Support\Composer\FakeComposerPackageInstaller;
use ArtisanPackUI\CMSFramework\Tests\Support\Composer\RecordingWebOpcacheResetter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * A PluginManager wired to the fake installer, with a chosen SAPI and a
 * recorded `opcache_reset()`, the way the updater tests fake them (#438).
 */
function opcacheProbeManager( FakeComposerPackageInstaller $installer, string $sapi, ?bool $resetResult = true ): PluginManager
{
    return new class ( $installer, $sapi, $resetResult ) extends PluginManager {
        /**
         * @var array<int, string>
         */
        public array $calls = [];

        public function __construct(
            private FakeComposerPackageInstaller $bound,
            private string $sapi,
            private ?bool $resetResult,
        ) {
            parent::__construct();
        }

        protected function composerPackageInstaller(): ComposerPackageInstallerInterface
        {
            return $this->bound;
        }

        protected function phpSapi(): string
        {
            return $this->sapi;
        }

        protected function attemptOpcacheReset(): ?bool
        {
            $this->calls[] = 'opcache-reset';

            return $this->resetResult;
        }

        protected function registerComposerPackageAutoloaders(): void
        {
            $this->calls[] = 'seat';

            parent::registerComposerPackageAutoloaders();
        }

        protected function runComposerPackageMigrationsInFreshProcess( Plugin $plugin ): void
        {
        }

        protected function runComposerPackageMigrations( Plugin $plugin ): void
        {
        }
    };
}

beforeEach( function (): void {
    Process::fake();
    Log::spy();

    config()->set( 'cms.plugins.autoInstallComposerDependencies', true );

    $this->installer                  = new FakeComposerPackageInstaller;
    $this->installer->installsToApply = [ 'artisanpack-ui/convertkit' => '1.4.0' ];

    $this->resetter = new RecordingWebOpcacheResetter;
    app()->instance( WebOpcacheResetter::class, $this->resetter );

    $this->manifest = [ 'composer' => [ 'artisanpack-ui/convertkit' => '^1.2' ] ];
} );

describe( 'after a plugin Composer install under PHP-FPM', function (): void {
    it( 'resets the shared cache in the request, without a second route to FPM', function (): void {
        $manager = opcacheProbeManager( $this->installer, 'fpm-fcgi' );

        expect( $manager->installComposerDependenciesForManifest( 'convertkit', $this->manifest ) )->toBeTrue()
            ->and( $manager->calls )->toBe( [ 'seat', 'opcache-reset' ] )
            ->and( $this->resetter->resets )->toBe( 0 );

        Log::shouldNotHaveReceived( 'warning' );
    } );

    it( 'resets once when an activation installs the packages', function (): void {
        Plugin::create( [
            'slug'      => 'convertkit',
            'name'      => 'ConvertKit',
            'version'   => '1.0.0',
            'is_active' => false,
            'meta'      => [ 'slug' => 'convertkit' ] + $this->manifest,
        ] );

        $manager = opcacheProbeManager( $this->installer, 'fpm-fcgi' );

        expect( $manager->activate( 'convertkit' ) )->toBeTrue()
            ->and( array_count_values( $manager->calls )['opcache-reset'] )->toBe( 1 );
    } );

    it( 'treats OPcache being off as nothing to reset', function (): void {
        $manager = opcacheProbeManager( $this->installer, 'fpm-fcgi', null );

        $manager->installComposerDependenciesForManifest( 'convertkit', $this->manifest );

        expect( $this->resetter->resets )->toBe( 0 );

        Log::shouldNotHaveReceived( 'warning' );
    } );

    it( 'warns the operator when the reset is refused, and keeps the install', function (): void {
        $manager = opcacheProbeManager( $this->installer, 'fpm-fcgi', false );

        expect( $manager->installComposerDependenciesForManifest( 'convertkit', $this->manifest ) )->toBeTrue();

        Log::shouldHaveReceived( 'warning' )->once()->withArgs(
            fn ( string $message, array $context ): bool => str_contains( $message, 'Reload PHP-FPM' )
                && 'convertkit' === $context['plugin'],
        );
    } );
} );

describe( 'after a plugin Composer install under the CLI', function (): void {
    it( 'reaches PHP-FPM through the web resetter', function ( string $sapi ): void {
        $manager = opcacheProbeManager( $this->installer, $sapi );

        expect( $manager->installComposerDependenciesForManifest( 'convertkit', $this->manifest ) )->toBeTrue()
            ->and( $this->resetter->resets )->toBe( 1 );

        Log::shouldHaveReceived( 'info' )->withArgs(
            fn ( string $message ): bool => str_contains( $message, 'reset PHP-FPM\'s OPcache' ) && str_contains( $message, 'fastcgi' ),
        );
        Log::shouldNotHaveReceived( 'warning' );
    } )->with( [ 'cli', 'phpdbg' ] );

    it( 'warns with why each mechanism failed when none reaches PHP-FPM', function (): void {
        $this->resetter->method         = null;
        $this->resetter->failureReasons = [ 'fastcgi' => "no socket\n\e[31m", 'loopback' => 'answered HTTP 500' ];

        $manager = opcacheProbeManager( $this->installer, 'cli' );

        expect( $manager->installComposerDependenciesForManifest( 'convertkit', $this->manifest ) )->toBeTrue();

        Log::shouldHaveReceived( 'warning' )->once()->withArgs(
            fn ( string $message, array $context ): bool => str_contains( $message, 'reload PHP-FPM' )
                && str_contains( $message, 'cms.updates.opcache.fpm_socket' )
                && 'convertkit' === $context['plugin']
                && [ 'fastcgi' => 'no socket[31m', 'loopback' => 'answered HTTP 500' ] === $context['attempts'],
        );
    } );
} );

describe( 'when the packages reach the disk but not the process', function (): void {
    it( 'still resets when the install cannot be verified, so a fresh request can finish it', function (): void {
        $this->installer->installsToApply = [];

        $manager = opcacheProbeManager( $this->installer, 'cli' );

        expect( fn () => $manager->installComposerDependenciesForManifest( 'convertkit', $this->manifest ) )
            ->toThrow( ComposerDependencyNotSatisfiedException::class );

        expect( $this->resetter->resets )->toBe( 1 );
    } );

    it( 'still resets when seating the packages throws, and rethrows', function (): void {
        $this->installer->autoloadMapsFailure = new RuntimeException( 'Autoload maps are unreadable.' );

        $manager = opcacheProbeManager( $this->installer, 'cli' );

        expect( fn () => $manager->installComposerDependenciesForManifest( 'convertkit', $this->manifest ) )
            ->toThrow( RuntimeException::class, 'Autoload maps are unreadable.' );

        expect( $this->resetter->resets )->toBe( 1 );
    } );
} );

describe( 'when nothing was installed', function (): void {
    it( 'does not reset', function ( string $sapi, string $scenario ): void {
        match ( $scenario ) {
            'install failed'    => $this->installer->failInstall = true,
            'already satisfied' => $this->installer->installed   = [ 'artisanpack-ui/convertkit' => '1.4.0' ],
            'auto-install off'  => config()->set( 'cms.plugins.autoInstallComposerDependencies', false ),
            default             => null,
        };

        $manager  = opcacheProbeManager( $this->installer, $sapi );
        $manifest = 'no composer block' === $scenario ? [] : $this->manifest;

        try {
            $manager->installComposerDependenciesForManifest( 'convertkit', $manifest );
        } catch ( ComposerDependencyNotSatisfiedException ) {
            // The failed install is the case under test.
        }

        expect( $manager->calls )->not->toContain( 'opcache-reset' )
            ->and( $this->resetter->resets )->toBe( 0 );
    } )->with( [ 'fpm-fcgi', 'cli' ] )
        ->with( [ 'install failed', 'already satisfied', 'no composer block', 'auto-install off' ] );
} );
