<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Exceptions;

use ArtisanPackUI\CMSFramework\Exceptions\CMSFrameworkException;

class PluginInstallationException extends CMSFrameworkException
{
    public static function extractionFailed( string $slug ): self
    {
        return new self( "Failed to extract plugin '{$slug}'." );
    }

    public static function alreadyInstalled( string $slug ): self
    {
        return new self( "Plugin '{$slug}' is already installed." );
    }

    /**
     * Build the exception for a plugin whose declared Composer packages could
     * not be installed while the plugin was being installed (#430).
     *
     * Reuses the Composer failure's message, which already names the plugin
     * and the reason, rather than wrapping it in a second sentence that says
     * the same thing. The one message written for activation ("Retry the
     * activation") is reworded for the install it now reports on. The original
     * exception is kept as `previous` for its resolution result.
     *
     * @since 2.16.0
     *
     * @param  string  $slug  Plugin being installed.
     * @param  ComposerDependencyNotSatisfiedException  $previous  The Composer-package failure.
     *
     * @return self
     */
    public static function composerDependenciesFailed( string $slug, ComposerDependencyNotSatisfiedException $previous ): self
    {
        $message = str_replace( 'Retry the activation', 'Retry the install', $previous->getMessage() );

        if ( ! str_contains( $message, "'{$slug}'" ) ) {
            $message = "Plugin '{$slug}' could not be installed: {$message}";
        }

        return new self( $message, 0, $previous );
    }
}
