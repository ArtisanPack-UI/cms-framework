<?php

declare( strict_types=1 );

namespace PluginWithUninstaller;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Models\Plugin;
use RuntimeException;

class ThrowingUninstall
{
    public function __invoke( Plugin $plugin ): void
    {
        throw new RuntimeException( 'Uninstall exploded.' );
    }
}
