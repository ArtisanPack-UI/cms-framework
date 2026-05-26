<?php

/**
 * Theme Validation Exception
 *
 * Exception thrown when a theme ZIP or manifest fails validation.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

/**
 * Theme Validation Exception class.
 *
 * Thrown when a theme ZIP upload fails pre-extraction validation
 * (MIME type, size, integrity, missing manifest) or when the parsed
 * theme.json manifest does not satisfy the required schema.
 *
 * @since 2.0.0
 */
class ThemeValidationException extends CMSFrameworkException
{
    /**
     * Creates a new exception for an invalid theme manifest.
     *
     * @since 2.0.0
     *
     * @param  string  $reason  Human-readable description of the failure.
     *
     * @return self The exception instance.
     */
    public static function invalidManifest( string $reason ): self
    {
        return new self( "Theme manifest validation failed: {$reason}" );
    }

    /**
     * Creates a new exception for an invalid theme ZIP file.
     *
     * @since 2.0.0
     *
     * @param  string  $reason  Human-readable description of the failure.
     *
     * @return self The exception instance.
     */
    public static function invalidZip( string $reason ): self
    {
        return new self( "Theme ZIP validation failed: {$reason}" );
    }
}
