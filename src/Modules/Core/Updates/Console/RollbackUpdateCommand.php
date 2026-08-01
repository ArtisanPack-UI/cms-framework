<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Console;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Managers\ApplicationUpdateManager;
use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support\ResolvesConfiguredPaths;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Rollback Update Command
 *
 * Console command to rollback to a previous backup.
 *
 * @since 1.0.0
 */
class RollbackUpdateCommand extends Command
{
    use ResolvesConfiguredPaths;

    /**
     * The name and signature of the console command.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $signature = 'update:rollback
                            {backup? : Path to specific backup file (optional)}
                            {--force : Skip confirmation prompt}
                            {--allow-external : Permit a backup path outside the configured backup directory}';

    /**
     * The console command description.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $description = 'Rollback to a previous backup';

    /**
     * Execute the console command.
     *
     * @since 1.0.0
     */
    public function handle( ApplicationUpdateManager $manager ): int
    {
        try {
            $backupPath = $this->argument( 'backup' );

            // If no backup specified, find the latest
            if ( ! $backupPath ) {
                $backupDir = $this->backupDirectory();
                $backups   = glob( "{$backupDir}/backup-*.zip" );

                if ( false === $backups || empty( $backups ) ) {
                    $this->error( 'No backups found.' );

                    return self::FAILURE;
                }

                // Sort by modification time (newest first)
                usort( $backups, fn ( $a, $b ) => filemtime( $b ) <=> filemtime( $a ) );

                $backupPath = $backups[0];
            }

            if ( ! File::exists( $backupPath ) ) {
                $this->error( "Backup not found: {$backupPath}" );

                return self::FAILURE;
            }

            // Rollback extracts an arbitrary ZIP over base_path() and then
            // runs `composer install`, which executes scripts from the
            // restored `composer.json`. Any other vulnerability yielding a
            // file write under storage/ would otherwise let an attacker plant
            // a backup and wait for an operator to restore it.
            if ( ! $this->option( 'allow-external' ) && ! $this->isWithinBackupDirectory( $backupPath ) ) {
                $this->error( 'Refusing to restore a backup from outside the configured backup directory.' );
                $this->line( 'Configured directory: ' . $this->backupDirectory() );
                $this->line( 'Pass --allow-external if this path is genuinely trusted.' );

                return self::FAILURE;
            }

            // Show backup information
            $this->newLine();
            $this->line( "Backup file: {$backupPath}" );
            $this->line( 'Backup size: ' . File::size( $backupPath ) . ' bytes' );
            $this->line( 'Created:     ' . date( 'Y-m-d H:i:s', filemtime( $backupPath ) ) );
            $this->newLine();

            // Confirm rollback
            if ( ! $this->option( 'force' ) ) {
                if ( ! $this->confirm( 'Do you want to proceed with the rollback?' ) ) {
                    $this->info( 'Rollback cancelled.' );

                    return self::SUCCESS;
                }
            }

            // Perform rollback
            $this->newLine();
            $this->warn( '⚠ Starting rollback process...' );
            $this->line( 'This may take several minutes. Do not interrupt the process.' );
            $this->newLine();

            $manager->rollback( $backupPath );

            $this->newLine();
            $this->info( '✓ Rollback completed successfully!' );
            $this->line( 'Application restored from backup.' );

            return self::SUCCESS;
        } catch ( Exception $e ) {
            $this->newLine();
            $this->error( '✗ Rollback failed:' );
            $this->error( $e->getMessage() );

            return self::FAILURE;
        }
    }

    /**
     * Absolute path to the configured backup directory.
     *
     * @since 2.7.1
     *
     * @return string Resolved backup directory.
     */
    protected function backupDirectory(): string
    {
        return $this->resolveConfiguredPath(
            (string) config( 'cms.updates.backup_path', 'backups/application' ),
        );
    }

    /**
     * Whether a backup path sits inside the configured backup directory.
     *
     * @since 2.7.1
     *
     * @param  string  $backupPath  Path to check.
     *
     * @return bool True when the path is contained by the backup directory.
     */
    protected function isWithinBackupDirectory( string $backupPath ): bool
    {
        $realBackupDir = realpath( $this->backupDirectory() );
        $realPath      = realpath( $backupPath );

        if ( false === $realBackupDir || false === $realPath ) {
            return false;
        }

        return str_starts_with( $realPath, $realBackupDir . DIRECTORY_SEPARATOR );
    }
}
