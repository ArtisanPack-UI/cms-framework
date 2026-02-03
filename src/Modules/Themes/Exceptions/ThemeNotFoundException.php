<?php

/**
 * Theme Not Found Exception
 *
 * Exception thrown when a requested theme cannot be found.
 *
 * @since      1.0.0
 */

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

/**
 * Theme Not Found Exception class.
 *
 * Thrown when attempting to access or activate a theme that does not exist
 * in the themes directory or fails validation.
 *
 * @since 1.0.0
 */
class ThemeNotFoundException extends CMSFrameworkException
{
    /**
     * Creates a new exception for a theme not found by slug.
     *
     * Factory method to generate a ThemeNotFoundException with a
     * descriptive error message including the theme slug.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  The theme slug that was not found.
     *
     * @return self The exception instance.
     */
    public static function forSlug( string $slug ): self
    {
        return new self( "Theme with slug '{$slug}' was not found." );
    }
}
