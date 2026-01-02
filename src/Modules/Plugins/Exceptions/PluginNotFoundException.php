<?php

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

class PluginNotFoundException extends CMSFrameworkException
{
    public static function forSlug( string $slug ): self
    {
        return new self( "Plugin with slug '{$slug}' not found." );
    }
}
