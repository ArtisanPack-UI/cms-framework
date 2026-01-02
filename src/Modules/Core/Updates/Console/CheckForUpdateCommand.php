<?php

declare( strict_types = 1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use Exception;
use Illuminate\Console\Command;

/**
 * Check For Update Command
 *
 * Console command to check for available updates.
 *
 * @since 1.0.0
 */
class CheckForUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $signature = 'update:check
                            {--clear-cache : Clear the update check cache before checking}';

    /**
     * The console command description.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $description = 'Check for available application updates';

    /**
     * Execute the console command.
     *
     * @since 1.0.0
     */
    public function handle( ApplicationUpdateManager $manager ): int
    {
        $this->info( 'Checking for updates...' );

        if ( $this->option( 'clear-cache' ) ) {
            $manager->clearCache();
            $this->line( 'Cache cleared.' );
        }

        try {
            $updateInfo = $manager->checkForUpdate();

            if ( $updateInfo->hasUpdate ) {
                $this->newLine();
                $this->info( '✓ Update available!' );
                $this->line( "Current version: {$updateInfo->currentVersion}" );
                $this->line( "Latest version:  {$updateInfo->latestVersion}" );

                if ( $updateInfo->releaseDate ) {
                    $this->line( "Release date: {$updateInfo->releaseDate}" );
                }

                if ( $updateInfo->changelog ) {
                    $this->newLine();
                    $this->line( 'Changelog:' );
                    $this->line( $updateInfo->changelog );
                }

                $this->newLine();
                $this->comment( 'Run "php artisan update:perform" to install the update.' );

                return self::SUCCESS;
            }

            $this->info( '✓ You are running the latest version.' );

            return self::SUCCESS;
        } catch ( Exception $e ) {
            $this->error( '✗ Failed to check for updates:' );
            $this->error( $e->getMessage() );

            return self::FAILURE;
        }
    }
}
