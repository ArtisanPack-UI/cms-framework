<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\IncompatiblePluginException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Managers\PluginManager;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;

beforeEach( function (): void {
    $this->manager = app( PluginManager::class );
    $this->admin   = TestUser::factory()->create();
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
        ->assertJsonPath( 'required_version', '999.0.0' );
} );
