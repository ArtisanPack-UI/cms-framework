<?php

declare( strict_types=1 );

namespace PluginWithUninstaller;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use Illuminate\Support\Facades\File;

class Uninstall
{
    public function __invoke( Plugin $plugin ): void
    {
        doAction( 'test.pluginWithUninstaller.uninstalled', $plugin->slug, File::isDirectory( $plugin->getPath() ) );
    }
}
