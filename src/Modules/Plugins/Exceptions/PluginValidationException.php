<?php

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

class PluginValidationException extends CMSFrameworkException
{
    public static function invalidManifest( string $reason ): self
    {
        return new self( "Plugin manifest validation failed: {$reason}" );
    }

    public static function invalidZip( string $reason ): self
    {
        return new self( "Plugin ZIP validation failed: {$reason}" );
    }
}
