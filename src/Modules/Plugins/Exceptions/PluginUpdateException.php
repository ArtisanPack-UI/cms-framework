<?php

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

class PluginUpdateException extends CMSFrameworkException
{
    public static function downloadFailed( string $slug ): self
    {
        return new self( "Failed to download update for plugin '{$slug}'." );
    }

    public static function backupFailed( string $slug ): self
    {
        return new self( "Failed to create backup for plugin '{$slug}'." );
    }
}
