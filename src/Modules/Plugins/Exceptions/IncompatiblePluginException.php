<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

/**
 * Raised when a plugin's declared minimum version is newer than what the host
 * provides: either `min_framework_version` (the cms-framework package version)
 * or `min_app_version` (the host application's `cms.plugins.appVersion`).
 *
 * @since 2.4.0
 */
class IncompatiblePluginException extends CMSFrameworkException
{
    /**
     * Constraint value for a failed `min_framework_version` check.
     *
     * @since 2.14.0
     */
    public const CONSTRAINT_FRAMEWORK = 'framework';

    /**
     * Constraint value for a failed `min_app_version` check.
     *
     * @since 2.14.0
     */
    public const CONSTRAINT_APP = 'app';

    /**
     * Slug of the plugin that failed the compatibility check.
     *
     * @since 2.4.0
     */
    public readonly string $pluginSlug;

    /**
     * The minimum version the plugin manifest declared.
     *
     * @since 2.4.0
     */
    public readonly string $requiredVersion;

    /**
     * The version currently installed in the host: the cms-framework version
     * for a framework constraint, the host application's for an app one.
     *
     * @since 2.4.0
     */
    public readonly string $hostVersion;

    /**
     * Which constraint failed: `framework` or `app`.
     *
     * @since 2.14.0
     */
    public readonly string $constraint;

    /**
     * Construct with pre-resolved version metadata.
     *
     * @since 2.4.0
     */
    public function __construct( string $slug, string $requiredVersion, string $hostVersion, string $constraint = self::CONSTRAINT_FRAMEWORK )
    {
        parent::__construct(
            self::CONSTRAINT_APP === $constraint
                ? "Plugin '{$slug}' requires host application version {$requiredVersion} or higher; host is running {$hostVersion}."
                : "Plugin '{$slug}' requires cms-framework {$requiredVersion} or higher; host is running cms-framework {$hostVersion}.",
        );
        $this->pluginSlug      = $slug;
        $this->requiredVersion = $requiredVersion;
        $this->hostVersion     = $hostVersion;
        $this->constraint      = $constraint;
    }

    /**
     * Build the exception for an unmet `min_framework_version`.
     *
     * @since 2.4.0
     *
     * @param  string  $slug  Plugin slug.
     * @param  string  $requiredVersion  Semver declared as min_framework_version.
     * @param  string  $hostVersion  cms-framework version resolved from the host.
     *
     * @return self
     */
    public static function forVersion( string $slug, string $requiredVersion, string $hostVersion ): self
    {
        return new self( $slug, $requiredVersion, $hostVersion, self::CONSTRAINT_FRAMEWORK );
    }

    /**
     * Build the exception for an unmet `min_app_version`.
     *
     * @since 2.14.0
     *
     * @param  string  $slug  Plugin slug.
     * @param  string  $requiredVersion  Semver declared as min_app_version.
     * @param  string  $appVersion  Host application version from `cms.plugins.appVersion`.
     *
     * @return self
     */
    public static function forAppVersion( string $slug, string $requiredVersion, string $appVersion ): self
    {
        return new self( $slug, $requiredVersion, $appVersion, self::CONSTRAINT_APP );
    }
}
