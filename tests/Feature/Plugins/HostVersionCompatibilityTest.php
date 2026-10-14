<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\IncompatiblePluginException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach( function (): void {
    $this->manager = app( PluginManager::class );
    $this->admin   = grantPermissions( TestUser::factory()->create(), 'manage-plugins' );

    $this->makePlugin = function ( array $meta, string $slug = 'versioned-plugin' ): Plugin {
        return Plugin::create( [
            'slug'      => $slug,
            'name'      => 'Versioned Plugin',
            'version'   => '1.0.0',
            'is_active' => false,
            'meta'      => array_merge( ['slug' => $slug], $meta ),
        ] );
    };

    $this->skipped = [];
    addAction( 'ap.cmsFramework.plugin.versionCheckSkipped', function ( string $slug, array $details ): void {
        $this->skipped[] = ['slug' => $slug] + $details;
    } );
} );

afterEach( function (): void {
    removeAllActions( 'ap.cmsFramework.plugin.versionCheckSkipped' );
} );

it( 'refuses to activate a plugin requiring a newer host version', function (): void {
    Plugin::create( [
        'slug'      => 'future-plugin',
        'name'      => 'Future Plugin',
        'version'   => '1.0.0',
        'is_active' => false,
        'meta'      => [
            'slug'             => 'future-plugin',
            'name'             => 'Future Plugin',
            'version'          => '1.0.0',
            'min_host_version' => '999.0.0',
        ],
    ] );

    try {
        $this->manager->activate( 'future-plugin' );
        $this->fail( 'Expected IncompatiblePluginException was not thrown.' );
    } catch ( IncompatiblePluginException $e ) {
        expect( $e->pluginSlug )->toBe( 'future-plugin' )
            ->and( $e->requiredVersion )->toBe( '999.0.0' )
            ->and( $e->hostVersion )->not->toBe( '' );
    }

    $plugin = Plugin::where( 'slug', 'future-plugin' )->first();
    expect( $plugin->is_active )->toBeFalse();
} );

it( 'activates when no min_host_version is declared', function (): void {
    Plugin::create( [
        'slug'      => 'legacy-plugin',
        'name'      => 'Legacy Plugin',
        'version'   => '1.0.0',
        'is_active' => false,
        'meta'      => ['slug' => 'legacy-plugin'],
    ] );

    expect( $this->manager->activate( 'legacy-plugin' ) )->toBeTrue();
} );

it( 'returns a 409 with a structured payload when activation fails on incompatibility', function (): void {
    Plugin::create( [
        'slug'      => 'future-plugin',
        'name'      => 'Future Plugin',
        'version'   => '1.0.0',
        'is_active' => false,
        'meta'      => [
            'slug'             => 'future-plugin',
            'min_host_version' => '999.0.0',
        ],
    ] );

    $response = $this->actingAs( $this->admin )->postJson( '/api/v1/plugins/future-plugin/activate' );

    $response->assertStatus( 409 )
        ->assertJsonPath( 'code', 'plugin_incompatible' )
        ->assertJsonPath( 'plugin', 'future-plugin' )
        ->assertJsonPath( 'constraint', 'framework' )
        ->assertJsonPath( 'required_version', '999.0.0' );
} );

describe( 'min_framework_version', function (): void {
    it( 'refuses activation when cms-framework is older than required', function (): void {
        ( $this->makePlugin )( ['min_framework_version' => '999.0.0'] );

        try {
            $this->manager->activate( 'versioned-plugin' );
            $this->fail( 'Expected IncompatiblePluginException was not thrown.' );
        } catch ( IncompatiblePluginException $e ) {
            expect( $e->constraint )->toBe( IncompatiblePluginException::CONSTRAINT_FRAMEWORK )
                ->and( $e->requiredVersion )->toBe( '999.0.0' )
                ->and( $e->getMessage() )->toContain( 'requires cms-framework 999.0.0' );
        }

        expect( Plugin::where( 'slug', 'versioned-plugin' )->first()->is_active )->toBeFalse();
    } );

    it( 'activates when cms-framework satisfies the minimum', function (): void {
        ( $this->makePlugin )( ['min_framework_version' => '0.0.1'] );

        expect( $this->manager->activate( 'versioned-plugin' ) )->toBeTrue();
    } );

    it( 'still enforces the deprecated min_host_version alias against cms-framework', function (): void {
        ( $this->makePlugin )( ['min_host_version' => '999.0.0'] );

        expect( fn () => $this->manager->activate( 'versioned-plugin' ) )
            ->toThrow( IncompatiblePluginException::class, 'requires cms-framework 999.0.0' );
    } );

    it( 'skips the check and fires versionCheckSkipped when cms-framework is not a comparable semver', function (): void {
        $manager = new class extends PluginManager {
            protected function resolveHostFrameworkVersion(): ?string
            {
                return 'dev-main';
            }
        };
        ( $this->makePlugin )( ['min_framework_version' => '999.0.0'] );

        expect( $manager->activate( 'versioned-plugin' ) )->toBeTrue()
            ->and( $this->skipped )->toBe( [[
                'slug'             => 'versioned-plugin',
                'constraint'       => 'framework',
                'required_version' => '999.0.0',
                'host_version'     => 'dev-main',
                'reason'           => 'not_semver',
            ]] );
    } );
} );

describe( 'min_app_version', function (): void {
    it( 'refuses activation when the host application is older than required', function (): void {
        config( ['cms.plugins.appVersion' => '1.2.0'] );
        ( $this->makePlugin )( ['min_app_version' => '1.3.0'] );

        try {
            $this->manager->activate( 'versioned-plugin' );
            $this->fail( 'Expected IncompatiblePluginException was not thrown.' );
        } catch ( IncompatiblePluginException $e ) {
            expect( $e->constraint )->toBe( IncompatiblePluginException::CONSTRAINT_APP )
                ->and( $e->requiredVersion )->toBe( '1.3.0' )
                ->and( $e->hostVersion )->toBe( '1.2.0' )
                ->and( $e->getMessage() )->toContain( 'requires host application version 1.3.0' );
        }

        expect( Plugin::where( 'slug', 'versioned-plugin' )->first()->is_active )->toBeFalse();
    } );

    it( 'activates when the host application satisfies the minimum', function ( string $appVersion ): void {
        config( ['cms.plugins.appVersion' => $appVersion] );
        ( $this->makePlugin )( ['min_app_version' => '1.2.0'] );

        expect( $this->manager->activate( 'versioned-plugin' ) )->toBeTrue()
            ->and( $this->skipped )->toBe( [] );
    } )->with( ['equal' => '1.2.0', 'newer' => '1.10.0', 'prefixed' => 'v2.0.0'] );

    it( 'is not compared against the cms-framework version', function (): void {
        config( ['cms.plugins.appVersion' => '1.0.0'] );
        ( $this->makePlugin )( ['min_app_version' => '2.0.0'] );

        expect( fn () => $this->manager->activate( 'versioned-plugin' ) )
            ->toThrow( IncompatiblePluginException::class, 'requires host application version 2.0.0' );
    } );

    it( 'skips the check and fires versionCheckSkipped when the host sets no appVersion', function ( mixed $appVersion ): void {
        config( ['cms.plugins.appVersion' => $appVersion] );
        ( $this->makePlugin )( ['min_app_version' => '999.0.0'] );

        expect( $this->manager->activate( 'versioned-plugin' ) )->toBeTrue()
            ->and( $this->skipped )->toBe( [[
                'slug'             => 'versioned-plugin',
                'constraint'       => 'app',
                'required_version' => '999.0.0',
                'host_version'     => null,
                'reason'           => 'unresolved',
            ]] );
    } )->with( ['null' => null, 'empty' => '', 'non-string' => 2] );

    it( 'skips the check when appVersion is not a comparable semver', function (): void {
        config( ['cms.plugins.appVersion' => 'dev-main'] );
        ( $this->makePlugin )( ['min_app_version' => '999.0.0'] );

        expect( $this->manager->activate( 'versioned-plugin' ) )->toBeTrue()
            ->and( $this->skipped[0]['reason'] )->toBe( 'not_semver' )
            ->and( $this->skipped[0]['host_version'] )->toBe( 'dev-main' );
    } );

    it( 'does not fire versionCheckSkipped when no minimum is declared', function (): void {
        ( $this->makePlugin )( [] );

        expect( $this->manager->activate( 'versioned-plugin' ) )->toBeTrue()
            ->and( $this->skipped )->toBe( [] );
    } );

    it( 'returns a 409 naming the app constraint', function (): void {
        config( ['cms.plugins.appVersion' => '1.0.0'] );
        ( $this->makePlugin )( ['min_app_version' => '2.0.0'] );

        $this->actingAs( $this->admin )->postJson( '/api/v1/plugins/versioned-plugin/activate' )
            ->assertStatus( 409 )
            ->assertJsonPath( 'code', 'plugin_incompatible' )
            ->assertJsonPath( 'constraint', 'app' )
            ->assertJsonPath( 'required_version', '2.0.0' )
            ->assertJsonPath( 'host_version', '1.0.0' );
    } );
} );
