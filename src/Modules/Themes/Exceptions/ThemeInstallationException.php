<?php

/**
 * Theme Installation Exception
 *
 * Exception thrown when a theme ZIP cannot be extracted or installed.
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

/**
 * Theme Installation Exception class.
 *
 * Thrown when a theme ZIP passes validation but cannot be successfully
 * extracted into the themes directory, or when the resulting theme
 * conflicts with an already-installed theme of the same slug.
 *
 * @since 1.2.0
 */
class ThemeInstallationException extends CMSFrameworkException
{
    /**
     * Creates a new exception for a failed extraction.
     *
     * @since 1.2.0
     *
     * @param  string  $slug  The theme slug (or 'unknown' if unresolved).
     *
     * @return self The exception instance.
     */
    public static function extractionFailed( string $slug ): self
    {
        return new self( "Failed to extract theme '{$slug}'." );
    }

    /**
     * Creates a new exception for an already-installed theme.
     *
     * @since 1.2.0
     *
     * @param  string  $slug  The conflicting theme slug.
     *
     * @return self The exception instance.
     */
    public static function alreadyInstalled( string $slug ): self
    {
        return new self( "Theme '{$slug}' is already installed." );
    }

    /**
     * Creates a new exception for a ZIP-slip / path-traversal attempt.
     *
     * @since 1.2.0
     *
     * @param  string  $entry  The offending ZIP entry name.
     *
     * @return self The exception instance.
     */
    public static function pathTraversal( string $entry ): self
    {
        return new self( "Theme ZIP contains an entry that escapes the themes directory: {$entry}" );
    }
}
