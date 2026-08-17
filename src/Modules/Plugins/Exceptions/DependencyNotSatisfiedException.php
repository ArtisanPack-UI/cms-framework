<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;
use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\DependencyResult;

/**
 * Raised when a plugin cannot be activated because its declared dependencies
 * are missing, inactive, or fail their version constraints, or when a plugin
 * cannot be deactivated because active plugins still depend on it.
 *
 * @since 2.9.0
 */
class DependencyNotSatisfiedException extends CMSFrameworkException
{
    /**
     * Slug of the plugin whose dependency check failed.
     *
     * @since 2.9.0
     */
    public readonly string $pluginSlug;

    /**
     * The full dependency result, when the failure came from an activation
     * check. Null for the deactivation (active-dependents) case.
     *
     * @since 2.9.0
     */
    public readonly ?DependencyResult $result;

    /**
     * Slugs of active plugins blocking a deactivation. Empty for activation
     * failures.
     *
     * @since 2.9.0
     *
     * @var array<int,string>
     */
    public readonly array $dependents;

    /**
     * Construct with pre-resolved dependency metadata.
     *
     * @since 2.9.0
     *
     * @param  string  $message  Human-readable failure message.
     * @param  string  $slug  Plugin slug the failure concerns.
     * @param  DependencyResult|null  $result  Activation dependency result, if any.
     * @param  array<int,string>  $dependents  Blocking active dependents, if any.
     */
    public function __construct( string $message, string $slug, ?DependencyResult $result = null, array $dependents = [] )
    {
        parent::__construct( $message );
        $this->pluginSlug = $slug;
        $this->result     = $result;
        $this->dependents = $dependents;
    }

    /**
     * Build the exception for a failed activation dependency check.
     *
     * @since 2.9.0
     *
     * @param  string  $slug  Plugin being activated.
     * @param  DependencyResult  $result  The unsatisfied dependency result.
     *
     * @return self
     */
    public static function forResult( string $slug, DependencyResult $result ): self
    {
        return new self(
            "Plugin '{$slug}' cannot be activated because its dependencies are not satisfied.",
            $slug,
            $result,
        );
    }

    /**
     * Build the exception for a deactivation blocked by active dependents.
     *
     * @since 2.9.0
     *
     * @param  string  $slug  Plugin being deactivated.
     * @param  array<int,string>  $dependents  Active plugins that depend on it.
     *
     * @return self
     */
    public static function hasActiveDependents( string $slug, array $dependents ): self
    {
        $list = implode( ', ', $dependents );

        return new self(
            "Plugin '{$slug}' cannot be deactivated because these active plugins depend on it: {$list}.",
            $slug,
            null,
            $dependents,
        );
    }
}
