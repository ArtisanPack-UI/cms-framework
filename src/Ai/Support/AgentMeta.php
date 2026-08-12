<?php

/**
 * Declared-metadata reader for AI agent classes.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.8.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Ai\Support;

use ArtisanPackUI\Ai\Agents\ArtisanPackAgent;
use ReflectionClass;

/**
 * Reads `$featureKey` / `$package` off an agent *class* without building
 * one.
 *
 * Deliberately not `app( $agentClass )->featureKey`, for two reasons:
 *
 * 1. **It cannot throw.** The trigger surfaces need the feature key to
 *    build their error envelopes, so resolving it must not be able to
 *    fail on the very path that reports failures. Container resolution
 *    can fail — `docs/AI-Features.md` tells hosts to bind a subclass over
 *    an agent, and a subclass is free to declare constructor
 *    dependencies that do not resolve.
 * 2. **It matches the declared class.** A host-bound override may carry a
 *    different `$featureKey`. Reading it from the container would key the
 *    registry by the override while {@see \ArtisanPackUI\CMSFramework\CMSFrameworkServiceProvider::AI_FEATURE_KEYS}
 *    still lists the original, so the two would disagree. The AI
 *    package's own guidance is that a host wanting the registry pointed
 *    at its subclass declares it explicitly in `aiFeatures()`, not by
 *    side effect of a container binding.
 *
 * Reflecting default property values is cheap and side-effect free: the
 * class is already autoloaded by the time its name reaches here.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.8.0
 *
 * @internal
 */
final class AgentMeta
{
    /**
     * Cached default-property lookups, keyed by agent class name.
     *
     * @var array<class-string, array<string, mixed>>
     */
    private static array $defaults = [];

    /**
     * The feature key an agent class declares.
     *
     * @since 2.8.0
     *
     * @param  class-string<ArtisanPackAgent>  $agentClass  Agent class to inspect.
     *
     * @return string Declared feature key, or an empty string when the agent declares none.
     */
    public static function featureKey( string $agentClass ): string
    {
        return self::stringDefault( $agentClass, 'featureKey' );
    }

    /**
     * The owning package an agent class declares.
     *
     * @since 2.8.0
     *
     * @param  class-string<ArtisanPackAgent>  $agentClass  Agent class to inspect.
     *
     * @return string Declared package name, or an empty string when the agent declares none.
     */
    public static function package( string $agentClass ): string
    {
        return self::stringDefault( $agentClass, 'package' );
    }

    /**
     * Read one string-typed default property off an agent class.
     *
     * @since 2.8.0
     *
     * @param  class-string<ArtisanPackAgent>  $agentClass  Agent class to inspect.
     * @param  string                          $property    Property name to read.
     *
     * @return string Declared value, or an empty string when absent or non-string.
     */
    private static function stringDefault( string $agentClass, string $property ): string
    {
        if ( ! isset( self::$defaults[ $agentClass ] ) ) {
            self::$defaults[ $agentClass ] = ( new ReflectionClass( $agentClass ) )->getDefaultProperties();
        }

        $value = self::$defaults[ $agentClass ][ $property ] ?? null;

        return is_string( $value ) ? $value : '';
    }
}
