<?php

declare( strict_types = 1 );

use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;

it( 'can create a plugin', function (): void {
    $plugin = Plugin::create( [
        'slug'         => 'test-plugin',
        'name'         => 'Test Plugin',
        'version'      => '1.0.0',
        'is_active'    => false,
        'meta'         => ['author' => 'Test Author'],
        'installed_at' => now(),
    ] );

    expect( $plugin )->toBeInstanceOf( Plugin::class )
        ->and( $plugin->slug )->toBe( 'test-plugin' )
        ->and( $plugin->name )->toBe( 'Test Plugin' )
        ->and( $plugin->version )->toBe( '1.0.0' )
        ->and( $plugin->is_active )->toBeFalse()
        ->and( $plugin->meta )->toBeArray()
        ->and( $plugin->meta['author'] )->toBe( 'Test Author' );
} );

it( 'casts is_active to boolean', function (): void {
    $plugin = Plugin::create( [
        'slug'      => 'test-plugin',
        'name'      => 'Test Plugin',
        'version'   => '1.0.0',
        'is_active' => 1,
    ] );

    expect( $plugin->is_active )->toBeTrue()
        ->and( $plugin->is_active )->toBeBool();
} );

it( 'casts meta to array', function (): void {
    $meta = [
        'author'      => 'Test Author',
        'description' => 'Test Description',
    ];

    $plugin = Plugin::create( [
        'slug'    => 'test-plugin',
        'name'    => 'Test Plugin',
        'version' => '1.0.0',
        'meta'    => $meta,
    ] );

    expect( $plugin->meta )->toBeArray()
        ->and( $plugin->meta )->toBe( $meta );
} );

it( 'casts installed_at to datetime', function (): void {
    $plugin = Plugin::create( [
        'slug'         => 'test-plugin',
        'name'         => 'Test Plugin',
        'version'      => '1.0.0',
        'installed_at' => '2024-01-01 12:00:00',
    ] );

    expect( $plugin->installed_at )->toBeInstanceOf( DateTime::class );
} );

it( 'has active scope', function (): void {
    Plugin::create( [
        'slug'      => 'active-plugin',
        'name'      => 'Active Plugin',
        'version'   => '1.0.0',
        'is_active' => true,
    ] );

    Plugin::create( [
        'slug'      => 'inactive-plugin',
        'name'      => 'Inactive Plugin',
        'version'   => '1.0.0',
        'is_active' => false,
    ] );

    $activePlugins = Plugin::active()->get();

    expect( $activePlugins )->toHaveCount( 1 )
        ->and( $activePlugins->first()->slug )->toBe( 'active-plugin' );
} );

it( 'returns correct path', function (): void {
    $plugin = Plugin::create( [
        'slug'    => 'test-plugin',
        'name'    => 'Test Plugin',
        'version' => '1.0.0',
    ] );

    $expectedPath = base_path( 'plugins/test-plugin' );

    expect( $plugin->getPath() )->toBe( $expectedPath );
} );

it( 'returns manifest from meta', function (): void {
    $meta = [
        'slug'        => 'test-plugin',
        'name'        => 'Test Plugin',
        'version'     => '1.0.0',
        'description' => 'Test Description',
    ];

    $plugin = Plugin::create( [
        'slug'    => 'test-plugin',
        'name'    => 'Test Plugin',
        'version' => '1.0.0',
        'meta'    => $meta,
    ] );

    expect( $plugin->getManifest() )->toBe( $meta );
} );

it( 'checks if has service provider', function (): void {
    $pluginWithSP = Plugin::create( [
        'slug'             => 'plugin-with-sp',
        'name'             => 'Plugin With SP',
        'version'          => '1.0.0',
        'service_provider' => 'TestPlugin\\ServiceProvider',
    ] );

    $pluginWithoutSP = Plugin::create( [
        'slug'    => 'plugin-without-sp',
        'name'    => 'Plugin Without SP',
        'version' => '1.0.0',
    ] );

    expect( $pluginWithSP->hasServiceProvider() )->toBeTrue()
        ->and( $pluginWithoutSP->hasServiceProvider() )->toBeFalse();
});
