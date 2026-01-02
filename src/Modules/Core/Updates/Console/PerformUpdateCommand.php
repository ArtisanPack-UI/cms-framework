<?php

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use Exception;
use Illuminate\Console\Command;

/**
 * Perform Update Command
 *
 * Console command to perform application update.
 *
 * @since 1.0.0
 */
class PerformUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $signature = 'update:perform
                            {--version= : Specific version to update to (default: latest)}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $description = 'Perform application update';

    /**
     * Execute the console command.
     *
     * @since 1.0.0
     */
    public function handle( ApplicationUpdateManager $manager ): int
    {
        try {
            // Check for updates first
            $updateInfo = $manager->checkForUpdate();

            if ( ! $updateInfo->hasUpdate ) {
                $this->info( '✓ You are already running the latest version.' );

                return self::SUCCESS;
            }

            $version = $this->option( 'version' ) ?? $updateInfo->latestVersion;

            // Show update information
            $this->newLine();
            $this->line( "Current version: {$updateInfo->currentVersion}" );
            $this->line( "Update to:       {$version}" );
            $this->newLine();

            // Confirm update
            if ( ! $this->option( 'force' ) ) {
                if ( ! $this->confirm( 'Do you want to proceed with the update?' ) ) {
                    $this->info( 'Update cancelled.' );

                    return self::SUCCESS;
                }
            }

            // Perform update
            $this->newLine();
            $this->warn( '⚠ Starting update process...' );
            $this->line( 'This may take several minutes. Do not interrupt the process.' );
            $this->newLine();

            $manager->performUpdate( $version );

            $this->newLine( 2 );
            $this->info( '✓ Update completed successfully!' );
            $this->line( "Application updated to version {$version}" );

            return self::SUCCESS;
        } catch ( Exception $e ) {
            $this->newLine();
            $this->error( '✗ Update failed:' );
            $this->error( $e->getMessage() );
            $this->newLine();
            $this->warn( 'If a backup was created, you can restore it using:' );
            $this->comment( 'php artisan update:rollback' );

            return self::FAILURE;
        }
    }
}
