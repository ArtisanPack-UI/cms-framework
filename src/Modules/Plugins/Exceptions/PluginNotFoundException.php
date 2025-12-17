<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions;

use Exception;

class PluginNotFoundException extends Exception
{
    public static function forSlug(string $slug): self
    {
        return new self("Plugin with slug '{$slug}' not found.");
    }
}
