<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Command for cleaning up old error logs and managing log file sizes
 */
class ErrorLogCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:error-logs:cleanup
		{--days=30 : Number of days to keep logs (default: 30)}
		{--max-size=100 : Maximum log file size in MB (default: 100)}
		{--compress : Compress old logs instead of deleting}
		{--force : Skip confirmation prompts}
		{--dry-run : Show what would be cleaned without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old error logs and manage log file sizes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $maxSize = (int) $this->option('max-size');
        $compress = $this->option('compress');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info('🧹 Starting error log cleanup...');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be modified');
            $this->newLine();
        }

        // Clean up by age
        $this->cleanupByAge($days, $compress, $force, $dryRun);

        // Clean up by size
        $this->cleanupBySize($maxSize, $compress, $force, $dryRun);

        // Clean up empty log files
        $this->cleanupEmptyFiles($force, $dryRun);

        // Optimize remaining logs
        $this->optimizeLogs($force, $dryRun);

        $this->newLine();
        $this->info('✅ Error log cleanup completed successfully!');

        return Command::SUCCESS;
    }

    /**
     * Clean up logs older than specified days
     */
    protected function cleanupByAge(int $days, bool $compress, bool $force, bool $dryRun): void
    {
        $this->info("🗓️  Cleaning up logs older than {$days} days...");

        $cutoffDate = Carbon::now()->subDays($days);
        $logPaths = $this->getLogPaths();
        $totalCleaned = 0;
        $totalSize = 0;

        foreach ($logPaths as $logPath) {
            if (! File::exists($logPath)) {
                continue;
            }

            $files = File::glob($logPath.'/*.log');

            foreach ($files as $file) {
                $fileDate = Carbon::createFromTimestamp(File::lastModified($file));

                if ($fileDate->lt($cutoffDate)) {
                    $fileSize = File::size($file);
                    $fileName = basename($file);

                    if (! $force && ! $dryRun) {
                        if (! $this->confirm("Delete old log file: {$fileName} (".$this->formatBytes($fileSize).')?')) {
                            continue;
                        }
                    }

                    if ($compress && ! $dryRun) {
                        $this->compressLogFile($file);
                        $this->line("  📦 Compressed: {$fileName}");
                    } elseif (! $dryRun) {
                        File::delete($file);
                        $this->line("  🗑️  Deleted: {$fileName}");
                    } else {
                        $this->line("  [DRY RUN] Would delete: {$fileName}");
                    }

                    $totalCleaned++;
                    $totalSize += $fileSize;
                }
            }
        }

        if ($totalCleaned > 0) {
            $this->info("  ✅ Processed {$totalCleaned} old log files (".$this->formatBytes($totalSize).')');
        } else {
            $this->line('  ℹ️  No old log files found');
        }
    }

    /**
     * Clean up logs that exceed maximum size
     */
    protected function cleanupBySize(int $maxSizeMB, bool $compress, bool $force, bool $dryRun): void
    {
        $this->info("📏 Cleaning up logs larger than {$maxSizeMB}MB...");

        $maxSizeBytes = $maxSizeMB * 1024 * 1024;
        $logPaths = $this->getLogPaths();
        $totalCleaned = 0;

        foreach ($logPaths as $logPath) {
            if (! File::exists($logPath)) {
                continue;
            }

            $files = File::glob($logPath.'/*.log');

            foreach ($files as $file) {
                $fileSize = File::size($file);

                if ($fileSize > $maxSizeBytes) {
                    $fileName = basename($file);

                    if (! $force && ! $dryRun) {
                        if (! $this->confirm("Process large log file: {$fileName} (".$this->formatBytes($fileSize).')?')) {
                            continue;
                        }
                    }

                    if (! $dryRun) {
                        if ($compress) {
                            $this->compressLogFile($file);
                            $this->line("  📦 Compressed large file: {$fileName}");
                        } else {
                            $this->truncateLogFile($file, $maxSizeBytes);
                            $this->line("  ✂️  Truncated large file: {$fileName}");
                        }
                    } else {
                        $this->line("  [DRY RUN] Would process large file: {$fileName}");
                    }

                    $totalCleaned++;
                }
            }
        }

        if ($totalCleaned > 0) {
            $this->info("  ✅ Processed {$totalCleaned} large log files");
        } else {
            $this->line('  ℹ️  No large log files found');
        }
    }

    /**
     * Clean up empty log files
     */
    protected function cleanupEmptyFiles(bool $force, bool $dryRun): void
    {
        $this->info('🗂️  Cleaning up empty log files...');

        $logPaths = $this->getLogPaths();
        $totalCleaned = 0;

        foreach ($logPaths as $logPath) {
            if (! File::exists($logPath)) {
                continue;
            }

            $files = File::glob($logPath.'/*.log');

            foreach ($files as $file) {
                if (File::size($file) === 0) {
                    $fileName = basename($file);

                    if (! $force && ! $dryRun) {
                        if (! $this->confirm("Delete empty log file: {$fileName}?")) {
                            continue;
                        }
                    }

                    if (! $dryRun) {
                        File::delete($file);
                        $this->line("  🗑️  Deleted empty file: {$fileName}");
                    } else {
                        $this->line("  [DRY RUN] Would delete empty file: {$fileName}");
                    }

                    $totalCleaned++;
                }
            }
        }

        if ($totalCleaned > 0) {
            $this->info("  ✅ Cleaned up {$totalCleaned} empty log files");
        } else {
            $this->line('  ℹ️  No empty log files found');
        }
    }

    /**
     * Optimize remaining log files
     */
    protected function optimizeLogs(bool $force, bool $dryRun): void
    {
        $this->info('⚡ Optimizing remaining log files...');

        $logPaths = $this->getLogPaths();
        $totalOptimized = 0;

        foreach ($logPaths as $logPath) {
            if (! File::exists($logPath)) {
                continue;
            }

            $files = File::glob($logPath.'/*.log');

            foreach ($files as $file) {
                $originalSize = File::size($file);

                if ($originalSize === 0) {
                    continue;
                }

                if (! $dryRun) {
                    // Remove duplicate consecutive entries and empty lines
                    $content = File::get($file);
                    $lines = explode("\n", $content);
                    $uniqueLines = [];
                    $lastLine = null;

                    foreach ($lines as $line) {
                        $trimmed = trim($line);
                        if ($trimmed !== '' && $trimmed !== $lastLine) {
                            $uniqueLines[] = $line;
                            $lastLine = $trimmed;
                        }
                    }

                    $optimizedContent = implode("\n", $uniqueLines);
                    File::put($file, $optimizedContent);

                    $newSize = File::size($file);
                    $savedBytes = $originalSize - $newSize;

                    if ($savedBytes > 0) {
                        $fileName = basename($file);
                        $this->line("  ⚡ Optimized: {$fileName} (saved ".$this->formatBytes($savedBytes).')');
                        $totalOptimized++;
                    }
                } else {
                    $fileName = basename($file);
                    $this->line("  [DRY RUN] Would optimize: {$fileName}");
                    $totalOptimized++;
                }
            }
        }

        if ($totalOptimized > 0) {
            $this->info("  ✅ Optimized {$totalOptimized} log files");
        } else {
            $this->line('  ℹ️  No optimization needed');
        }
    }

    /**
     * Get all log paths to check
     */
    protected function getLogPaths(): array
    {
        return [
            storage_path('logs'),
            storage_path('logs/cms'),
            storage_path('logs/errors'),
            storage_path('logs/audit'),
        ];
    }

    /**
     * Compress a log file
     */
    protected function compressLogFile(string $file): void
    {
        $compressedFile = $file.'.gz';

        $content = File::get($file);
        $compressed = gzencode($content, 9);

        File::put($compressedFile, $compressed);
        File::delete($file);
    }

    /**
     * Truncate a log file to keep only recent entries
     */
    protected function truncateLogFile(string $file, int $maxSize): void
    {
        $content = File::get($file);
        $lines = explode("\n", $content);

        // Keep last portion that fits within size limit
        $truncatedLines = [];
        $currentSize = 0;

        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $lineSize = strlen($lines[$i]) + 1; // +1 for newline
            if ($currentSize + $lineSize > $maxSize * 0.8) { // Keep 80% of max size
                break;
            }
            array_unshift($truncatedLines, $lines[$i]);
            $currentSize += $lineSize;
        }

        $truncatedContent = implode("\n", $truncatedLines);
        File::put($file, $truncatedContent);
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }
}
