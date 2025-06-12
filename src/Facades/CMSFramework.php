<?php
/**
 * CMSFramework Facade
 *
 * Provides a static interface to the CMSFramework functionality.
 * This facade acts as a proxy to the underlying CMSManager instance.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Facades
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * CMSFramework Facade
 *
 * Provides a static interface to access the CMSFramework functionality.
 * This facade proxies static method calls to the underlying CMSManager instance.
 *
 * @since 1.0.0
 * @see \ArtisanPackUI\CMSFramework\CMSManager
 */
class CMSFramework extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * Returns the service container binding key for the CMSFramework service.
     * This method is used by the Facade base class to resolve the correct instance.
     *
     * @since 1.0.0
     * @return string The service container binding key.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cmsframework';
    }
}
