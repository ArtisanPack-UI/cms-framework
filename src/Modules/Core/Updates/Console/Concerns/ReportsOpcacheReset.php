<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console\Concerns;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;

/**
 * Reports Opcache Reset
 *
 * Shared console output for `update:perform` and `update:rollback`, which
 * run outside PHP-FPM and so reach its OPcache over FastCGI, the loopback
 * route or the reload command (#429). Says so loudly when none of them
 * worked (#425).
 *
 * @since 2.16.0
 */
trait ReportsOpcacheReset
{
    /**
     * Tell the operator whether the web server's OPcache was reset, and how.
     *
     * @since 2.16.0
     *
     * @param  ApplicationUpdateManager  $manager  Update manager.
     * @param  string  $staleCode  What PHP-FPM keeps serving without a reload.
     */
    protected function reportOpcacheReset( ApplicationUpdateManager $manager, string $staleCode ): void
    {
        $method = $manager->opcacheResetMethod();

        if ( null !== $method && 'web-request' !== $method ) {
            $this->line( "OPcache reset for the web server ({$method})." );

            return;
        }

        if ( $manager->phpReloadRequired() ) {
            $this->newLine();
            $this->error( '⚠ PHP-FPM\'s OPcache could not be reset.' );
            $this->warn( "Reload PHP-FPM if OPcache runs with opcache.validate_timestamps=0, or it keeps serving {$staleCode}." );
            $this->line( 'To have the updater do this, set cms.updates.opcache.fpm_socket or cms.updates.php_reload_command. See the log for why each attempt failed.' );
        }
    }
}
