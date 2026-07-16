<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

/**
 * Raised when a plugin's declared min_host_version is newer than the framework
 * version installed in the host application.
 *
 * @since 2.4.0
 */
class IncompatiblePluginException extends CMSFrameworkException
{
    /**
     * Slug of the plugin that failed the compatibility check.
     *
     * @since 2.4.0
     */
    public readonly string $pluginSlug;

    /**
     * The min_host_version the plugin manifest declared.
     *
     * @since 2.4.0
     */
    public readonly string $requiredVersion;

    /**
     * The framework version currently installed in the host.
     *
     * @since 2.4.0
     */
    public readonly string $hostVersion;

    /**
     * Construct with pre-resolved version metadata.
     *
     * @since 2.4.0
     */
    public function __construct( string $slug, string $requiredVersion, string $hostVersion )
    {
        parent::__construct(
            "Plugin '{$slug}' requires cms-framework {$requiredVersion} or higher; host is running {$hostVersion}.",
        );
        $this->pluginSlug      = $slug;
        $this->requiredVersion = $requiredVersion;
        $this->hostVersion     = $hostVersion;
    }

    /**
     * Named-constructor factory that matches the shape used by sibling plugin
     * exceptions.
     *
     * @since 2.4.0
     *
     * @param  string  $slug  Plugin slug.
     * @param  string  $requiredVersion  Semver declared as min_host_version.
     * @param  string  $hostVersion  Framework version resolved from the host.
     *
     * @return self
     */
    public static function forVersion( string $slug, string $requiredVersion, string $hostVersion ): self
    {
        return new self( $slug, $requiredVersion, $hostVersion );
    }
}
