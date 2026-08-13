<?php

declare( strict_types=1 );

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
                            {--target-version= : Specific version to update to (default: latest)}
                            {--allow-downgrade : Permit a target version that is not newer than the installed one}
                            {--queue : Dispatch the update to a queue worker instead of running it here}
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

            if ( ! $updateInfo->hasUpdate() ) {
                $this->info( '✓ You are already running the latest version.' );

                return self::SUCCESS;
            }

            $version = $this->option( 'target-version' ) ?? $updateInfo->latestVersion;

            // Show update information
            $this->newLine();
            $this->line( "Current version: {$updateInfo->resolveCurrentVersion()}" );
            $this->line( "Update to:       {$version}" );
            $this->newLine();

            // Confirm update
            if ( ! $this->option( 'force' ) ) {
                if ( ! $this->confirm( 'Do you want to proceed with the update?' ) ) {
                    $this->info( 'Update cancelled.' );

                    return self::SUCCESS;
                }
            }

            if ( $this->option( 'queue' ) ) {
                // The resolved version rather than the raw option: the operator
                // has just confirmed a prompt naming this version, so pinning it
                // is what they agreed to. Leaving it null would let the job
                // install whatever is latest by the time a worker picks it up.
                $manager->dispatchUpdate( $version, (bool) $this->option( 'allow-downgrade' ) );

                $this->newLine();
                $this->info( '✓ Update queued.' );
                $this->line( 'A queue worker will run it. Poll `php artisan update:status` for progress.' );

                return self::SUCCESS;
            }

            // Perform update
            $this->newLine();
            $this->warn( '⚠ Starting update process...' );
            $this->line( 'This may take several minutes. Do not interrupt the process.' );
            $this->newLine();

            $manager->performUpdate( $version, (bool) $this->option( 'allow-downgrade' ) );

            $this->newLine( 2 );
            $this->info( '✓ Update completed successfully!' );
            $this->line( "Application updated to version {$version}" );

            return self::SUCCESS;
        } catch ( Exception $e ) {
            $this->newLine();
            $this->error( '✗ Update failed:' );
            $this->error( $e->getMessage() );

            // Suppressed only when nothing ever reached the application tree —
            // a refused queue driver, say — because pointing the operator at
            // `update:rollback` would send them to restore a snapshot over a
            // healthy install. Gated on the recorded step rather than on
            // `--queue`: with `cms.updates.queue.allow_sync` the dispatch runs
            // the entire update inline, so a `--queue` failure can absolutely
            // mean the tree was rewritten and a backup exists.
            if ( $this->updateTouchedTheTree( $manager ) ) {
                $this->newLine();
                $this->warn( 'If a backup was created, you can restore it using:' );
                $this->comment( 'php artisan update:rollback' );
            }

            return self::FAILURE;
        }
    }

    /**
     * Whether the failed run got far enough to modify the installation.
     *
     * @since 2.8.0
     *
     * @param  ApplicationUpdateManager  $manager  Update manager.
     *
     * @return bool True when a step was recorded as having started.
     */
    protected function updateTouchedTheTree( ApplicationUpdateManager $manager ): bool
    {
        $state = $manager->updateState();

        return is_string( $state['step'] ?? null ) && '' !== $state['step'];
    }
}
