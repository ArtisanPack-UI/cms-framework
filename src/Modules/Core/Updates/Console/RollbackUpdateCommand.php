<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Rollback Update Command
 *
 * Console command to rollback to a previous backup.
 *
 * @since 2.0.0
 */
class RollbackUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @since 2.0.0
     *
     * @var string
     */
    protected $signature = 'update:rollback
                            {backup? : Path to specific backup file (optional)}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @since 2.0.0
     *
     * @var string
     */
    protected $description = 'Rollback to a previous backup';

    /**
     * Execute the console command.
     *
     * @since 2.0.0
     */
    public function handle(ApplicationUpdateManager $manager): int
    {
        try {
            $backupPath = $this->argument('backup');

            // If no backup specified, find the latest
            if (! $backupPath) {
                $backupDir = storage_path(config('cms.updates.backup_path', 'backups/application'));
                $backups   = glob("{$backupDir}/backup-*.zip");

                if (false === $backups || empty($backups)) {
                    $this->error('No backups found.');

                    return self::FAILURE;
                }

                // Sort by modification time (newest first)
                usort($backups, fn ($a, $b) => filemtime($b) <=> filemtime($a));

                $backupPath = $backups[0];
            }

            if (! File::exists($backupPath)) {
                $this->error("Backup not found: {$backupPath}");

                return self::FAILURE;
            }

            // Show backup information
            $this->newLine();
            $this->line("Backup file: {$backupPath}");
            $this->line('Backup size: '.File::size($backupPath).' bytes');
            $this->line('Created:     '.date('Y-m-d H:i:s', filemtime($backupPath)));
            $this->newLine();

            // Confirm rollback
            if (! $this->option('force')) {
                if (! $this->confirm('Do you want to proceed with the rollback?')) {
                    $this->info('Rollback cancelled.');

                    return self::SUCCESS;
                }
            }

            // Perform rollback
            $this->newLine();
            $this->warn('⚠ Starting rollback process...');
            $this->line('This may take several minutes. Do not interrupt the process.');
            $this->newLine();

            $manager->rollback($backupPath);

            $this->newLine();
            $this->info('✓ Rollback completed successfully!');
            $this->line('Application restored from backup.');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->newLine();
            $this->error('✗ Rollback failed:');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
