<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

/**
 * Raised when resolving a plugin activation order encounters a dependency
 * cycle (e.g. plugin A requires B and B requires A).
 *
 * @since 2.9.0
 */
class CircularDependencyException extends CMSFrameworkException
{
    /**
     * The slugs forming the detected cycle, in traversal order with the
     * repeated slug appended to close the loop.
     *
     * @since 2.9.0
     *
     * @var array<int,string>
     */
    public readonly array $cycle;

    /**
     * Construct with the offending cycle.
     *
     * @since 2.9.0
     *
     * @param  array<int,string>  $cycle  The slugs forming the cycle.
     */
    public function __construct( array $cycle )
    {
        $path = implode( ' -> ', $cycle );
        parent::__construct( "Circular plugin dependency detected: {$path}." );
        $this->cycle = $cycle;
    }

    /**
     * Named-constructor factory matching sibling plugin exceptions.
     *
     * @since 2.9.0
     *
     * @param  array<int,string>  $cycle  The slugs forming the cycle.
     *
     * @return self
     */
    public static function forCycle( array $cycle ): self
    {
        return new self( $cycle );
    }
}
