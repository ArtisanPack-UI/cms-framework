<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

class PluginUpdateException extends CMSFrameworkException
{
    /**
     * The update archive could not be retrieved, or the update failed for a
     * reason with no more specific factory.
     */
    public static function downloadFailed( string $slug ): self
    {
        return new self( "Failed to download update for plugin '{$slug}'." );
    }

    /**
     * The pre-update backup could not be written, so the update was not
     * attempted.
     */
    public static function backupFailed( string $slug ): self
    {
        return new self( "Failed to create backup for plugin '{$slug}'." );
    }

    /**
     * An update failed for a reason worth surfacing verbatim — a checksum
     * mismatch, or an archive whose source published no digest to verify
     * against.
     */
    public static function updateFailed( string $slug, string $reason ): self
    {
        return new self( "Failed to update plugin '{$slug}': {$reason}" );
    }
}
