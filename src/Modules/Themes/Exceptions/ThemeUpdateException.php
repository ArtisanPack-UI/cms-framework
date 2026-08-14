<?php

/**
 * Theme Update Exception
 *
 * Exception thrown when an installed theme cannot be updated in place.
 *
 * @since      2.8.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

/**
 * Theme Update Exception class.
 *
 * Mirrors {@see \ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions\PluginUpdateException}
 * for the theme update path: the archive could not be retrieved, the backup
 * could not be written, the staged directory could not be swapped into place,
 * or the theme declares no update source to check against.
 *
 * @since 2.8.0
 */
class ThemeUpdateException extends CMSFrameworkException
{
    /**
     * The pre-update backup could not be written, so the update was not
     * attempted.
     *
     * @since 2.8.0
     *
     * @param  string  $slug  The theme slug.
     *
     * @return self The exception instance.
     */
    public static function backupFailed( string $slug ): self
    {
        return new self( "Failed to create backup for theme '{$slug}'." );
    }

    /**
     * The theme's manifest declares no usable `update` source, so there is
     * nothing to check or download from.
     *
     * @since 2.8.0
     *
     * @param  string  $slug  The theme slug.
     *
     * @return self The exception instance.
     */
    public static function noUpdateSource( string $slug ): self
    {
        return new self( "Theme '{$slug}' declares no update source in its theme.json." );
    }

    /**
     * The validated staging directory could not be moved into place over the
     * installed theme.
     *
     * @since 2.8.0
     *
     * @param  string  $slug  The theme slug.
     *
     * @return self The exception instance.
     */
    public static function swapFailed( string $slug ): self
    {
        return new self( "Failed to swap the updated files into place for theme '{$slug}'." );
    }

    /**
     * An update failed for a reason worth surfacing verbatim — a checksum
     * mismatch, or an archive whose source published no digest to verify
     * against.
     *
     * @since 2.8.0
     *
     * @param  string  $slug  The theme slug.
     * @param  string  $reason  The underlying failure reason.
     *
     * @return self The exception instance.
     */
    public static function updateFailed( string $slug, string $reason ): self
    {
        return new self( "Failed to update theme '{$slug}': {$reason}" );
    }
}
