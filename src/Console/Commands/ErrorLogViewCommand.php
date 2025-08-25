<?php

declare(strict_types=1);

/**
 * Error Log View Command
 *
 * Artisan command for viewing and analyzing error logs in the CMS framework.
 * Provides filtering, searching, and formatted display of error logs with
 * various output formats and analysis capabilities.
 *
 * @since 1.0.0
 * @author Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Services\StructuredLoggerService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Error Log Viewing Command
 *
 * Provides comprehensive error log viewing and analysis capabilities.
 */
class ErrorLogViewCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:logs:view
                            {--level=* : Filter by log level (emergency, alert, critical, error, warning, notice, info, debug)}
                            {--category=* : Filter by category (plugin, media, content, user, authorization, etc.)}
                            {--since= : Show logs since this date (e.g., "1 hour ago", "2024-01-01")}
                            {--until= : Show logs until this date}
                            {--limit=50 : Maximum number of log entries to display}
                            {--search= : Search for specific text in log messages}
                            {--format=table : Output format (table, json, csv, detailed)}
                            {--export= : Export results to file}
                            {--follow : Follow log file (like tail -f)}';

    /**
     * The console command description.
     */
    protected $description = 'View and analyze error logs with filtering and search capabilities';

    /**
     * Structured logger service.
     */
    private StructuredLoggerService $logger;

    /**
     * Create a new command instance.
     */
    public function __construct(StructuredLoggerService $logger)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('follow')) {
            return $this->followLogs();
        }

        $logs = $this->getLogs();

        if (empty($logs)) {
            $this->info('No log entries found matching the specified criteria.');
            return 0;
        }

        $this->displayLogs($logs);

        if ($exportPath = $this->option('export')) {
            $this->exportLogs($logs, $exportPath);
        }

        return 0;
    }

    /**
     * Get filtered log entries.
     */
    private function getLogs(): array
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (!File::exists($logFile)) {
            $this->error('Log file not found: ' . $logFile);
            return [];
        }

        $content = File::get($logFile);
        $entries = $this->parseLogEntries($content);

        return $this->filterLogs($entries);
    }

    /**
     * Parse log file content into structured entries.
     */
    private function parseLogEntries(string $content): array
    {
        $entries = [];
        $lines = explode("\n", $content);
        $currentEntry = null;

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Check if this is a new log entry (starts with date/time)
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                // Save previous entry if exists
                if ($currentEntry) {
                    $entries[] = $currentEntry;
                }

                // Start new entry
                $currentEntry = [
                    'timestamp' => $matches[1],
                    'raw_line' => $line,
                    'message' => '',
                    'level' => 'unknown',
                    'category' => 'system',
                    'context' => [],
                ];

                // Parse log level and details
                $this->parseLogLine($currentEntry, $line);
            } else {
                // Continuation of previous entry (stack trace, etc.)
                if ($currentEntry) {
                    $currentEntry['raw_line'] .= "\n" . $line;
                    $currentEntry['message'] .= "\n" . $line;
                }
            }
        }

        // Add the last entry
        if ($currentEntry) {
            $entries[] = $currentEntry;
        }

        return array_reverse($entries); // Most recent first
    }

    /**
     * Parse individual log line for details.
     */
    private function parseLogLine(array &$entry, string $line): void
    {
        // Extract log level
        if (preg_match('/\] (\w+)\.(\w+): (.+)/', $line, $matches)) {
            $entry['level'] = strtolower($matches[2]);
            $entry['message'] = $matches[3];
            
            // Try to extract category from structured logs
            if (strpos($matches[3], 'category') !== false) {
                if (preg_match('/"category":"([^"]+)"/', $matches[3], $categoryMatches)) {
                    $entry['category'] = $categoryMatches[1];
                }
            }
        } elseif (preg_match('/\] (\w+): (.+)/', $line, $matches)) {
            $entry['level'] = strtolower($matches[1]);
            $entry['message'] = $matches[2];
        }

        // Extract context if it's JSON
        if (strpos($entry['message'], '{') !== false) {
            $jsonStart = strpos($entry['message'], '{');
            $jsonPart = substr($entry['message'], $jsonStart);
            
            $context = json_decode($jsonPart, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $entry['context'] = $context;
                $entry['message'] = trim(substr($entry['message'], 0, $jsonStart));
            }
        }
    }

    /**
     * Filter logs based on command options.
     */
    private function filterLogs(array $entries): array
    {
        $filtered = $entries;

        // Filter by log level
        if ($levels = $this->option('level')) {
            $levels = array_map('strtolower', $levels);
            $filtered = array_filter($filtered, fn($entry) => in_array($entry['level'], $levels));
        }

        // Filter by category
        if ($categories = $this->option('category')) {
            $filtered = array_filter($filtered, fn($entry) => in_array($entry['category'], $categories));
        }

        // Filter by date range
        if ($since = $this->option('since')) {
            $sinceDate = Carbon::parse($since);
            $filtered = array_filter($filtered, function ($entry) use ($sinceDate) {
                return Carbon::parse($entry['timestamp'])->gte($sinceDate);
            });
        }

        if ($until = $this->option('until')) {
            $untilDate = Carbon::parse($until);
            $filtered = array_filter($filtered, function ($entry) use ($untilDate) {
                return Carbon::parse($entry['timestamp'])->lte($untilDate);
            });
        }

        // Search in message content
        if ($search = $this->option('search')) {
            $filtered = array_filter($filtered, function ($entry) use ($search) {
                return stripos($entry['message'], $search) !== false ||
                       stripos($entry['raw_line'], $search) !== false;
            });
        }

        // Apply limit
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $filtered = array_slice($filtered, 0, $limit);
        }

        return array_values($filtered);
    }

    /**
     * Display logs in the specified format.
     */
    private function displayLogs(array $logs): void
    {
        $format = $this->option('format');

        match ($format) {
            'json' => $this->displayAsJson($logs),
            'csv' => $this->displayAsCsv($logs),
            'detailed' => $this->displayDetailed($logs),
            default => $this->displayAsTable($logs),
        };
    }

    /**
     * Display logs as table.
     */
    private function displayAsTable(array $logs): void
    {
        $headers = ['Time', 'Level', 'Category', 'Message'];
        $rows = [];

        foreach ($logs as $log) {
            $message = Str::limit($log['message'], 80);
            $time = Carbon::parse($log['timestamp'])->format('H:i:s');
            
            $rows[] = [
                $time,
                $this->colorizeLevel($log['level']),
                $log['category'],
                $message,
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Display logs as JSON.
     */
    private function displayAsJson(array $logs): void
    {
        $this->line(json_encode($logs, JSON_PRETTY_PRINT));
    }

    /**
     * Display logs as CSV.
     */
    private function displayAsCsv(array $logs): void
    {
        $this->line('Timestamp,Level,Category,Message');
        
        foreach ($logs as $log) {
            $message = str_replace(['"', "\n", "\r"], ['""', ' ', ' '], $log['message']);
            $this->line(sprintf('"%s","%s","%s","%s"',
                $log['timestamp'],
                $log['level'],
                $log['category'],
                $message
            ));
        }
    }

    /**
     * Display detailed view of logs.
     */
    private function displayDetailed(array $logs): void
    {
        foreach ($logs as $index => $log) {
            $this->line('');
            $this->line("=== Log Entry #" . ($index + 1) . " ===");
            $this->line("Time: " . $log['timestamp']);
            $this->line("Level: " . $this->colorizeLevel($log['level']));
            $this->line("Category: " . $log['category']);
            $this->line("Message: " . $log['message']);
            
            if (!empty($log['context'])) {
                $this->line("Context:");
                $this->line(json_encode($log['context'], JSON_PRETTY_PRINT));
            }
            
            if (count($logs) > 1 && $index < count($logs) - 1) {
                $this->line(str_repeat('-', 50));
            }
        }
    }

    /**
     * Export logs to file.
     */
    private function exportLogs(array $logs, string $path): void
    {
        $format = pathinfo($path, PATHINFO_EXTENSION) ?: 'json';
        
        $content = match ($format) {
            'csv' => $this->formatAsCsv($logs),
            'txt' => $this->formatAsText($logs),
            default => json_encode($logs, JSON_PRETTY_PRINT),
        };

        File::put($path, $content);
        $this->info("Logs exported to: {$path}");
    }

    /**
     * Format logs as CSV for export.
     */
    private function formatAsCsv(array $logs): string
    {
        $output = "Timestamp,Level,Category,Message\n";
        
        foreach ($logs as $log) {
            $message = str_replace(['"', "\n", "\r"], ['""', ' ', ' '], $log['message']);
            $output .= sprintf('"%s","%s","%s","%s"' . "\n",
                $log['timestamp'],
                $log['level'],
                $log['category'],
                $message
            );
        }
        
        return $output;
    }

    /**
     * Format logs as plain text for export.
     */
    private function formatAsText(array $logs): string
    {
        $output = '';
        
        foreach ($logs as $log) {
            $output .= "[{$log['timestamp']}] {$log['level']}.{$log['category']}: {$log['message']}\n";
        }
        
        return $output;
    }

    /**
     * Follow logs in real-time (like tail -f).
     */
    private function followLogs(): int
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (!File::exists($logFile)) {
            $this->error('Log file not found: ' . $logFile);
            return 1;
        }

        $this->info('Following log file: ' . $logFile);
        $this->info('Press Ctrl+C to stop...');
        $this->line('');

        $handle = fopen($logFile, 'r');
        fseek($handle, 0, SEEK_END);

        while (true) {
            $line = fgets($handle);
            
            if ($line === false) {
                usleep(100000); // 0.1 second
                continue;
            }

            // Parse and filter the line
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', trim($line))) {
                $entry = [
                    'timestamp' => '',
                    'level' => 'unknown',
                    'category' => 'system',
                    'message' => trim($line),
                    'raw_line' => trim($line),
                    'context' => [],
                ];
                
                $this->parseLogLine($entry, trim($line));
                
                // Apply filters
                if ($this->shouldDisplayEntry($entry)) {
                    $time = Carbon::parse($entry['timestamp'])->format('H:i:s');
                    $this->line(sprintf('[%s] %s.%s: %s',
                        $time,
                        $this->colorizeLevel($entry['level']),
                        $entry['category'],
                        $entry['message']
                    ));
                }
            }
        }

        fclose($handle);
        return 0;
    }

    /**
     * Check if entry should be displayed based on filters.
     */
    private function shouldDisplayEntry(array $entry): bool
    {
        // Apply level filter
        if ($levels = $this->option('level')) {
            $levels = array_map('strtolower', $levels);
            if (!in_array($entry['level'], $levels)) {
                return false;
            }
        }

        // Apply category filter
        if ($categories = $this->option('category')) {
            if (!in_array($entry['category'], $categories)) {
                return false;
            }
        }

        // Apply search filter
        if ($search = $this->option('search')) {
            if (stripos($entry['message'], $search) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Colorize log level for display.
     */
    private function colorizeLevel(string $level): string
    {
        return match ($level) {
            'emergency', 'alert', 'critical' => "<fg=red;options=bold>{$level}</>",
            'error' => "<fg=red>{$level}</>",
            'warning' => "<fg=yellow>{$level}</>",
            'notice', 'info' => "<fg=blue>{$level}</>",
            'debug' => "<fg=gray>{$level}</>",
            default => $level,
        };
    }
}