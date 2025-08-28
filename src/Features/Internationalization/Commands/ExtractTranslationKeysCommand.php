<?php

namespace ArtisanPackUI\CMSFramework\Features\Internationalization\Commands;

use ArtisanPackUI\CMSFramework\Features\Internationalization\Services\TranslationExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Exception;

/**
 * Extract Translation Keys Command
 *
 * Console command for extracting translation keys from source code files
 * using the TranslationExtractor service in the ArtisanPack UI CMS Framework.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Features\Internationalization\Commands
 * @since      1.5.0
 */
class ExtractTranslationKeysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @since 1.5.0
     *
     * @var string
     */
    protected $signature = 'i18n:extract
                           {paths* : Paths to scan for translation keys}
                           {--format=json : Export format (json, php, csv, pot)}
                           {--output= : Output file path}
                           {--namespace= : Filter by namespace}
                           {--no-recursive : Disable recursive directory scanning}
                           {--show-stats : Display extraction statistics}
                           {--patterns= : Additional extraction patterns (comma-separated)}
                           {--exclude= : Additional directories to exclude (comma-separated)}
                           {--dry-run : Show what would be extracted without saving}';

    /**
     * The console command description.
     *
     * @since 1.5.0
     *
     * @var string
     */
    protected $description = 'Extract translation keys from source code files';

    /**
     * The translation extractor service instance.
     *
     * @since 1.5.0
     *
     * @var TranslationExtractor
     */
    protected TranslationExtractor $extractor;

    /**
     * Create a new command instance.
     *
     * @since 1.5.0
     *
     * @param TranslationExtractor $extractor
     */
    public function __construct(TranslationExtractor $extractor)
    {
        parent::__construct();
        $this->extractor = $extractor;
    }

    /**
     * Execute the console command.
     *
     * @since 1.5.0
     *
     * @return int Command exit code
     */
    public function handle(): int
    {
        $this->info('🌍 Translation Key Extractor');
        $this->info('============================');
        $this->newLine();

        try {
            // Get and validate paths
            $paths = $this->argument('paths');
            if (empty($paths)) {
                $paths = [app_path(), resource_path('views')];
                $this->info('No paths specified. Using default paths: app/, resources/views/');
            }

            $validPaths = $this->validatePaths($paths);
            if (empty($validPaths)) {
                $this->error('No valid paths found to scan.');
                return 1;
            }

            // Prepare extraction options
            $options = $this->prepareOptions();

            // Add custom patterns if provided
            $this->addCustomPatterns();

            // Perform extraction
            $this->info('Extracting translation keys...');
            $startTime = microtime(true);

            $keys = $this->extractor->extract($validPaths, $options);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($keys->isEmpty()) {
                $this->warn('No translation keys found.');
                return 0;
            }

            // Display results
            $this->displayResults($keys, $executionTime);

            // Show statistics if requested
            if ($this->option('show-stats')) {
                $this->displayStatistics($keys);
            }

            // Export results
            if (!$this->option('dry-run')) {
                $this->exportResults($keys);
            } else {
                $this->info('DRY RUN: Results not saved.');
            }

            $this->newLine();
            $this->info('✅ Extraction completed successfully!');
            
            return 0;

        } catch (Exception $e) {
            $this->error('Extraction failed: ' . $e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }
            
            return 1;
        }
    }

    /**
     * Validate the provided paths.
     *
     * @since 1.5.0
     *
     * @param array $paths Paths to validate
     * @return array Valid paths
     */
    protected function validatePaths(array $paths): array
    {
        $validPaths = [];

        foreach ($paths as $path) {
            $fullPath = $this->resolveFullPath($path);
            
            if (File::exists($fullPath) || File::isDirectory($fullPath)) {
                $validPaths[] = $fullPath;
                $this->line("✓ Path: {$fullPath}");
            } else {
                $this->warn("✗ Invalid path: {$path}");
            }
        }

        return $validPaths;
    }

    /**
     * Resolve the full path from relative or absolute path.
     *
     * @since 1.5.0
     *
     * @param string $path Path to resolve
     * @return string Full path
     */
    protected function resolveFullPath(string $path): string
    {
        // If it's already an absolute path, return as-is
        if (str_starts_with($path, '/') || (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[A-Z]:\\\\/', $path))) {
            return $path;
        }

        // Try relative to base path
        $basePath = base_path($path);
        if (File::exists($basePath) || File::isDirectory($basePath)) {
            return $basePath;
        }

        // Return original path (will be marked as invalid later)
        return $path;
    }

    /**
     * Prepare extraction options from command arguments.
     *
     * @since 1.5.0
     *
     * @return array Options array
     */
    protected function prepareOptions(): array
    {
        $options = [
            'recursive' => !$this->option('no-recursive'),
            'unique' => true,
            'sort' => 'key',
        ];

        if ($this->option('namespace')) {
            $options['namespace'] = $this->option('namespace');
        }

        return $options;
    }

    /**
     * Add custom extraction patterns if provided.
     *
     * @since 1.5.0
     *
     * @return void
     */
    protected function addCustomPatterns(): void
    {
        if ($patternsOption = $this->option('patterns')) {
            $patterns = explode(',', $patternsOption);
            
            foreach ($patterns as $pattern) {
                $pattern = trim($pattern);
                if (!empty($pattern)) {
                    // Add to PHP patterns by default (can be extended)
                    $this->extractor->addPattern('php', $pattern);
                    $this->line("Added custom pattern: {$pattern}");
                }
            }
        }
    }

    /**
     * Display extraction results.
     *
     * @since 1.5.0
     *
     * @param \Illuminate\Support\Collection $keys Extracted keys
     * @param float $executionTime Execution time in milliseconds
     * @return void
     */
    protected function displayResults($keys, float $executionTime): void
    {
        $this->newLine();
        $this->info("📊 Extraction Results");
        $this->info("====================");
        $this->line("Keys found: {$keys->count()}");
        $this->line("Unique keys: {$keys->unique('key')->count()}");
        $this->line("Files scanned: {$keys->unique('file')->filter()->count()}");
        $this->line("Execution time: {$executionTime}ms");
        
        // Show file type breakdown
        $fileTypes = $keys->groupBy('type')->map->count();
        if ($fileTypes->isNotEmpty()) {
            $this->newLine();
            $this->line("File types:");
            foreach ($fileTypes as $type => $count) {
                $this->line("  {$type}: {$count} keys");
            }
        }

        // Show sample keys if not too many
        if ($keys->count() <= 20) {
            $this->newLine();
            $this->line("Sample keys:");
            foreach ($keys->take(10) as $key) {
                $file = $key['file'] ? basename($key['file']) : 'N/A';
                $this->line("  {$key['key']} ({$file}:{$key['line']})");
            }
        }
    }

    /**
     * Display extraction statistics.
     *
     * @since 1.5.0
     *
     * @param \Illuminate\Support\Collection $keys Extracted keys
     * @return void
     */
    protected function displayStatistics($keys): void
    {
        $stats = $this->extractor->generateStats($keys);

        $this->newLine();
        $this->info("📈 Detailed Statistics");
        $this->info("======================");
        
        // Basic stats
        $this->line("Total keys: {$stats['total_keys']}");
        $this->line("Unique keys: {$stats['unique_keys']}");
        $this->line("Namespaces: {$stats['namespaces']}");
        $this->line("Files scanned: {$stats['files_scanned']}");

        // File types breakdown
        if (!empty($stats['file_types'])) {
            $this->newLine();
            $this->line("File types breakdown:");
            foreach ($stats['file_types'] as $type => $count) {
                $this->line("  {$type}: {$count}");
            }
        }

        // Namespace breakdown
        if (!empty($stats['namespace_breakdown'])) {
            $this->newLine();
            $this->line("Namespace breakdown:");
            foreach ($stats['namespace_breakdown'] as $namespace => $count) {
                $this->line("  {$namespace}: {$count}");
            }
        }

        // Most used keys
        if (!empty($stats['most_used_keys'])) {
            $this->newLine();
            $this->line("Most frequently used keys:");
            foreach (array_slice($stats['most_used_keys'], 0, 5) as $key => $count) {
                $this->line("  {$key}: {$count} occurrences");
            }
        }
    }

    /**
     * Export extraction results.
     *
     * @since 1.5.0
     *
     * @param \Illuminate\Support\Collection $keys Extracted keys
     * @return void
     */
    protected function exportResults($keys): void
    {
        $format = $this->option('format');
        $outputPath = $this->option('output');

        // Generate default output path if not provided
        if (!$outputPath) {
            $timestamp = now()->format('Y-m-d_H-i-s');
            $outputPath = storage_path("app/translations/extracted_keys_{$timestamp}.{$format}");
            
            // Ensure directory exists
            File::ensureDirectoryExists(dirname($outputPath));
        }

        try {
            $content = $this->extractor->exportKeys($keys, $format);
            File::put($outputPath, $content);
            
            $this->newLine();
            $this->info("💾 Results exported to: {$outputPath}");
            $this->line("Format: {$format}");
            $this->line("Size: " . $this->formatBytes(strlen($content)));

        } catch (Exception $e) {
            $this->error("Export failed: " . $e->getMessage());
        }
    }

    /**
     * Format bytes to human readable format.
     *
     * @since 1.5.0
     *
     * @param int $bytes Number of bytes
     * @return string Formatted string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get example usage for the command.
     *
     * @since 1.5.0
     *
     * @return array Example commands
     */
    public function getExamples(): array
    {
        return [
            'Extract from app and views directories:',
            '  php artisan i18n:extract app resources/views',
            '',
            'Export to PHP format:',
            '  php artisan i18n:extract app --format=php --output=lang/en/extracted.php',
            '',
            'Filter by namespace and show stats:',
            '  php artisan i18n:extract app --namespace=auth --show-stats',
            '',
            'Dry run with custom patterns:',
            '  php artisan i18n:extract app --dry-run --patterns="/__t\([\'\""]([^\'"]+)[\'\""]/" --show-stats',
        ];
    }
}