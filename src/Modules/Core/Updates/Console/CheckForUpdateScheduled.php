<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled Update Check Command
 *
 * Runs automatically via scheduler to check for updates.
 *
 * @since 1.0.0
 */
class CheckForUpdateScheduled extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $signature = 'update:check-scheduled';

    /**
     * The console command description.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $description = 'Check for updates (scheduled task)';

    /**
     * Execute the console command.
     *
     * @since 1.0.0
     */
    public function handle( ApplicationUpdateManager $manager ): int
    {
        try {
            $updateInfo = $manager->checkForUpdate();

            if ( $updateInfo->hasUpdate() ) {
                // Log the available update
                Log::info( 'Update available', [
                    'current_version' => $updateInfo->resolveCurrentVersion(),
                    'latest_version'  => $updateInfo->latestVersion,
                    'release_date'    => $updateInfo->releaseDate,
                ] );

                // Auto-update if enabled
                if ( config( 'cms.updates.auto_update_enabled', false ) ) {
                    $this->info( 'Auto-update is enabled. Starting update process...' );

                    // `performUpdate()` returns true or throws — a false return
                    // is not a reachable outcome, so there is no failure branch
                    // here; a thrown failure is handled by the catch below.
                    $manager->performUpdate();

                    Log::info( 'Auto-update completed successfully', [
                        'version' => $updateInfo->latestVersion,
                    ] );

                    $this->info( 'Auto-update completed successfully!' );

                    return self::SUCCESS;
                }

                $this->info( "Update available: {$updateInfo->latestVersion}" );
            } else {
                $this->info( 'No updates available' );
            }

            return self::SUCCESS;
        } catch ( Exception $e ) {
            Log::error( 'Scheduled update check failed', [
                'error' => $e->getMessage(),
            ] );

            $this->error( "Failed: {$e->getMessage()}" );

            return self::FAILURE;
        }
    }
}
