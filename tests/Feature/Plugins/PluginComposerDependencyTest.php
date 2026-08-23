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
} );
