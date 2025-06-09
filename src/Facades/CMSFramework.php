<?php

namespace ArtisanPackUI\CMSFramework\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \ArtisanPackUI\CMSFramework\A11y
 */
class CMSFramework extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cmsframework';
    }
}
