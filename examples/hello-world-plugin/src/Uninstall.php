<?php

declare( strict_types=1 );

/**
 * Hello World Plugin uninstaller.
 *
 * Declared as `"uninstall": "HelloWorld\\Uninstall"` in `plugin.json`. The
 * framework invokes it when the plugin is deleted — whether or not the plugin
 * is active — before any opted-in migration reset, so the plugin's own tables
 * are still readable here. Clean up anything the plugin keeps outside its
 * tables: settings rows, cached tokens, files under `storage/`.
 *
 * @since 1.0.0
 */

namespace HelloWorld;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Illuminate\Support\Facades\Cache;

final class Uninstall
{
    /**
     * Remove data the plugin stored outside its own tables.
     *
     * @since 1.0.0
     *
     * @param  Plugin  $plugin  The plugin being deleted.
     */
    public function __invoke( Plugin $plugin ): void
    {
        Cache::forget( 'hello-world.greeting-count' );
    }
}
