<?php

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

class PluginInstallationException extends CMSFrameworkException
{
    public static function extractionFailed( string $slug ): self
    {
        return new self( "Failed to extract plugin '{$slug}'." );
    }

    public static function alreadyInstalled( string $slug ): self
    {
        return new self( "Plugin '{$slug}' is already installed." );
    }
}
