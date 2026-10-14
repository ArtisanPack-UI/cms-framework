<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Feature\Plugins;

use ArtisanPackUI\CMSFramework\Tests\TestCase;
use Illuminate\Support\Facades\Route;

/**
 * The `cms.plugins.assetRoute` opt-out.
 *
 * A class-based test because the route loads while the application boots, so
 * the config has to be set before that in `defineEnvironment()`.
 *
 * @since 2.14.0
 */
class PluginAssetRouteOptOutTest extends TestCase
{
    /**
     * A host that serves plugin assets itself can turn the framework route off.
     *
     * @since 2.14.0
     */
    public function test_the_plugin_asset_route_is_not_registered_when_disabled(): void
    {
        $this->assertFalse( Route::has( 'plugins.assets' ) );
        $this->assertTrue( Route::has( 'themes.assets' ) );
    }

    /**
     * Disable the plugin asset route.
     *
     * @since 2.14.0
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment( $app ): void
    {
        $app['config']->set( 'cms.plugins.assetRoute', false );
    }
}
