<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled Update Check Command
 *
 * Runs automatically via scheduler to check for updates.
 *
 * @since 2.0.0
 */
class CheckForUpdateScheduled extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @since 2.0.0
     *
     * @var string
     */
    protected $signature = 'update:check-scheduled';

    /**
     * The console command description.
     *
     * @since 2.0.0
     *
     * @var string
     */
    protected $description = 'Check for updates (scheduled task)';

    /**
     * Execute the console command.
     *
     * @since 2.0.0
     */
    public function handle(ApplicationUpdateManager $manager): int
    {
        try {
            $updateInfo = $manager->checkForUpdate();

            if ($updateInfo->hasUpdate) {
                // Store in cache for admin panel notification
                Cache::put('cms.update_available', $updateInfo->toArray(), now()->addDays(1));

                // Log the available update
                Log::info('Update available', [
                    'current_version' => $updateInfo->currentVersion,
                    'latest_version'  => $updateInfo->latestVersion,
                    'release_date'    => $updateInfo->releaseDate,
                ]);

                // Auto-update if enabled
                if (config('cms.updates.auto_update_enabled', false)) {
                    $this->info('Auto-update is enabled. Starting update process...');

                    $success = $manager->performUpdate();

                    if ($success) {
                        Log::info('Auto-update completed successfully', [
                            'version' => $updateInfo->latestVersion,
                        ]);

                        $this->info('Auto-update completed successfully!');

                        return self::SUCCESS;
                    }

                    Log::error('Auto-update failed');
                    $this->error('Auto-update failed');

                    return self::FAILURE;
                }

                $this->info("Update available: {$updateInfo->latestVersion}");
            } else {
                // Clear cache if no update available
                Cache::forget('cms.update_available');

                $this->info('No updates available');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            Log::error('Scheduled update check failed', [
                'error' => $e->getMessage(),
            ]);

            $this->error("Failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
