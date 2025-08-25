<?php

declare(strict_types=1);

/**
 * Error Analysis Command
 *
 * Artisan command for analyzing error logs and generating statistics and insights
 * about error patterns, frequency, and system health in the CMS framework.
 *
 * @since 1.0.0
 * @author Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Services\AuditLoggerService;
use ArtisanPackUI\CMSFramework\Services\StructuredLoggerService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Error Analysis Command
 *
 * Provides comprehensive error analysis and system health insights.
 */
class ErrorAnalysisCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:logs:analyze
                            {--period=7d : Analysis period (1h, 6h, 1d, 7d, 30d, 90d)}
                            {--format=table : Output format (table, json, report)}
                            {--export= : Export analysis to file}
                            {--include-trends : Include trend analysis}
                            {--show-details : Show detailed breakdown}
                            {--health-check : Perform system health check}';

    /**
     * The console command description.
     */
    protected $description = 'Analyze error logs and generate insights about system health and error patterns';

    /**
     * Structured logger service.
     */
    private StructuredLoggerService $logger;

    /**
     * Audit logger service.
     */
    private AuditLoggerService $auditLogger;

    /**
     * Create a new command instance.
     */
    public function __construct(StructuredLoggerService $logger, AuditLoggerService $auditLogger)
    {
        parent::__construct();
        $this->logger = $logger;
        $this->auditLogger = $auditLogger;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $period = $this->option('period');
        $this->info("Analyzing error logs for the past {$period}...");

        $analysis = $this->performAnalysis($period);

        if ($this->option('health-check')) {
            $healthStatus = $this->performHealthCheck($analysis);
            $analysis['health_status'] = $healthStatus;
        }

        if ($this->option('include-trends')) {
            $trends = $this->analyzeTrends($period);
            $analysis['trends'] = $trends;
        }

        $this->displayAnalysis($analysis);

        if ($exportPath = $this->option('export')) {
            $this->exportAnalysis($analysis, $exportPath);
        }

        return 0;
    }

    /**
     * Perform comprehensive error analysis.
     */
    private function performAnalysis(string $period): array
    {
        $since = $this->parsePeriod($period);
        $logs = $this->getLogsForAnalysis($since);

        $analysis = [
            'period' => $period,
            'since' => $since->toDateTimeString(),
            'total_entries' => count($logs),
            'summary' => $this->generateSummary($logs),
            'level_breakdown' => $this->analyzeLevels($logs),
            'category_breakdown' => $this->analyzeCategories($logs),
            'hourly_distribution' => $this->analyzeHourlyDistribution($logs),
            'top_errors' => $this->findTopErrors($logs),
            'error_patterns' => $this->analyzeErrorPatterns($logs),
            'user_impact' => $this->analyzeUserImpact($logs),
            'performance_impact' => $this->analyzePerformanceImpact($logs),
        ];

        if ($this->option('show-details')) {
            $analysis['detailed_breakdown'] = $this->generateDetailedBreakdown($logs);
        }

        return $analysis;
    }

    /**
     * Parse period string into Carbon instance.
     */
    private function parsePeriod(string $period): Carbon
    {
        $amount = (int) substr($period, 0, -1);
        $unit = substr($period, -1);

        return match ($unit) {
            'h' => now()->subHours($amount),
            'd' => now()->subDays($amount),
            'w' => now()->subWeeks($amount),
            'm' => now()->subMonths($amount),
            default => now()->subDays(7),
        };
    }

    /**
     * Get logs for analysis from various sources.
     */
    private function getLogsForAnalysis(Carbon $since): array
    {
        $logs = [];

        // Get logs from Laravel log file
        $logFile = storage_path('logs/laravel.log');
        if (File::exists($logFile)) {
            $logs = array_merge($logs, $this->parseLogFile($logFile, $since));
        }

        // Get audit logs from database if available
        try {
            $auditLogs = $this->getAuditLogsFromDatabase($since);
            $logs = array_merge($logs, $auditLogs);
        } catch (\Exception $e) {
            // Audit logs table might not exist
        }

        // Sort logs by timestamp
        usort($logs, fn($a, $b) => strcmp($a['timestamp'], $b['timestamp']));

        return $logs;
    }

    /**
     * Parse log file and extract entries since given time.
     */
    private function parseLogFile(string $logFile, Carbon $since): array
    {
        $content = File::get($logFile);
        $entries = [];
        $lines = explode("\n", $content);
        $currentEntry = null;

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Check if this is a new log entry
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                // Save previous entry if it's within our time range
                if ($currentEntry && Carbon::parse($currentEntry['timestamp'])->gte($since)) {
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
                    'source' => 'laravel_log',
                ];

                $this->parseLogLine($currentEntry, $line);
            } elseif ($currentEntry) {
                // Continuation of previous entry
                $currentEntry['raw_line'] .= "\n" . $line;
                $currentEntry['message'] .= "\n" . $line;
            }
        }

        // Add the last entry if it's within range
        if ($currentEntry && Carbon::parse($currentEntry['timestamp'])->gte($since)) {
            $entries[] = $currentEntry;
        }

        return array_filter($entries, fn($entry) => Carbon::parse($entry['timestamp'])->gte($since));
    }

    /**
     * Parse individual log line for analysis.
     */
    private function parseLogLine(array &$entry, string $line): void
    {
        // Extract log level and category
        if (preg_match('/\] (\w+)\.(\w+): (.+)/', $line, $matches)) {
            $entry['level'] = strtolower($matches[2]);
            $entry['message'] = $matches[3];
            
            // Extract category from structured logs
            if (strpos($matches[3], '"category":"') !== false) {
                if (preg_match('/"category":"([^"]+)"/', $matches[3], $categoryMatches)) {
                    $entry['category'] = $categoryMatches[1];
                }
            }
        } elseif (preg_match('/\] (\w+): (.+)/', $line, $matches)) {
            $entry['level'] = strtolower($matches[1]);
            $entry['message'] = $matches[2];
        }

        // Extract JSON context if present
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
     * Get audit logs from database.
     */
    private function getAuditLogsFromDatabase(Carbon $since): array
    {
        if (!DB::getSchemaBuilder()->hasTable('audit_logs')) {
            return [];
        }

        $auditLogs = DB::table('audit_logs')
            ->where('created_at', '>=', $since)
            ->get()
            ->map(function ($record) {
                $context = json_decode($record->context, true) ?? [];
                
                return [
                    'timestamp' => $record->created_at,
                    'message' => $record->action,
                    'level' => 'info',
                    'category' => $record->category,
                    'context' => $context,
                    'source' => 'audit_log',
                    'raw_line' => "[{$record->created_at}] info.{$record->category}: {$record->action}",
                ];
            })
            ->toArray();

        return $auditLogs;
    }

    /**
     * Generate summary statistics.
     */
    private function generateSummary(array $logs): array
    {
        $errorLevels = ['emergency', 'alert', 'critical', 'error'];
        $warningLevels = ['warning'];
        
        $errors = array_filter($logs, fn($log) => in_array($log['level'], $errorLevels));
        $warnings = array_filter($logs, fn($log) => in_array($log['level'], $warningLevels));
        
        return [
            'total_entries' => count($logs),
            'errors' => count($errors),
            'warnings' => count($warnings),
            'error_rate' => count($logs) > 0 ? round(count($errors) / count($logs) * 100, 2) : 0,
            'most_common_level' => $this->getMostCommon(array_column($logs, 'level')),
            'most_active_category' => $this->getMostCommon(array_column($logs, 'category')),
        ];
    }

    /**
     * Analyze log levels distribution.
     */
    private function analyzeLevels(array $logs): array
    {
        $levels = array_count_values(array_column($logs, 'level'));
        arsort($levels);
        
        return $levels;
    }

    /**
     * Analyze categories distribution.
     */
    private function analyzeCategories(array $logs): array
    {
        $categories = array_count_values(array_column($logs, 'category'));
        arsort($categories);
        
        return $categories;
    }

    /**
     * Analyze hourly distribution of errors.
     */
    private function analyzeHourlyDistribution(array $logs): array
    {
        $hourly = [];
        
        foreach ($logs as $log) {
            $hour = Carbon::parse($log['timestamp'])->format('H');
            $hourly[$hour] = ($hourly[$hour] ?? 0) + 1;
        }
        
        ksort($hourly);
        return $hourly;
    }

    /**
     * Find top recurring errors.
     */
    private function findTopErrors(array $logs, int $limit = 10): array
    {
        $errorLevels = ['emergency', 'alert', 'critical', 'error'];
        $errors = array_filter($logs, fn($log) => in_array($log['level'], $errorLevels));
        
        $errorMessages = [];
        foreach ($errors as $error) {
            $message = $this->normalizeErrorMessage($error['message']);
            $errorMessages[$message] = ($errorMessages[$message] ?? 0) + 1;
        }
        
        arsort($errorMessages);
        return array_slice($errorMessages, 0, $limit, true);
    }

    /**
     * Analyze error patterns and trends.
     */
    private function analyzeErrorPatterns(array $logs): array
    {
        $patterns = [
            'database_errors' => 0,
            'authentication_failures' => 0,
            'permission_denials' => 0,
            'plugin_errors' => 0,
            'media_upload_failures' => 0,
            'validation_errors' => 0,
        ];
        
        foreach ($logs as $log) {
            $message = strtolower($log['message']);
            $category = $log['category'];
            
            if (strpos($message, 'database') !== false || strpos($message, 'sql') !== false) {
                $patterns['database_errors']++;
            }
            if (strpos($message, 'authentication') !== false || strpos($message, 'unauthenticated') !== false) {
                $patterns['authentication_failures']++;
            }
            if (strpos($message, 'permission') !== false || strpos($message, 'authorization') !== false) {
                $patterns['permission_denials']++;
            }
            if ($category === 'plugin' || strpos($message, 'plugin') !== false) {
                $patterns['plugin_errors']++;
            }
            if ($category === 'media' || strpos($message, 'upload') !== false) {
                $patterns['media_upload_failures']++;
            }
            if (strpos($message, 'validation') !== false) {
                $patterns['validation_errors']++;
            }
        }
        
        return $patterns;
    }

    /**
     * Analyze user impact from errors.
     */
    private function analyzeUserImpact(array $logs): array
    {
        $userImpactLevels = ['emergency', 'alert', 'critical', 'error'];
        $impactingErrors = array_filter($logs, fn($log) => in_array($log['level'], $userImpactLevels));
        
        $userIds = [];
        foreach ($impactingErrors as $error) {
            if (isset($error['context']['user_id'])) {
                $userIds[] = $error['context']['user_id'];
            }
        }
        
        $affectedUsers = count(array_unique($userIds));
        
        return [
            'total_impacting_errors' => count($impactingErrors),
            'affected_users' => $affectedUsers,
            'average_errors_per_user' => $affectedUsers > 0 ? round(count($impactingErrors) / $affectedUsers, 2) : 0,
        ];
    }

    /**
     * Analyze performance impact.
     */
    private function analyzePerformanceImpact(array $logs): array
    {
        $performanceLogs = array_filter($logs, fn($log) => $log['category'] === 'performance');
        
        $executionTimes = [];
        foreach ($performanceLogs as $log) {
            if (isset($log['context']['execution_time'])) {
                $executionTimes[] = $log['context']['execution_time'];
            }
        }
        
        $avgExecutionTime = !empty($executionTimes) ? array_sum($executionTimes) / count($executionTimes) : 0;
        
        return [
            'performance_logs' => count($performanceLogs),
            'average_execution_time' => round($avgExecutionTime, 3),
            'max_execution_time' => !empty($executionTimes) ? max($executionTimes) : 0,
        ];
    }

    /**
     * Generate detailed breakdown.
     */
    private function generateDetailedBreakdown(array $logs): array
    {
        return [
            'by_day' => $this->groupByDay($logs),
            'by_hour' => $this->groupByHour($logs),
            'critical_errors' => $this->getCriticalErrors($logs),
            'recent_errors' => array_slice($logs, -10), // Last 10 errors
        ];
    }

    /**
     * Group logs by day.
     */
    private function groupByDay(array $logs): array
    {
        $days = [];
        foreach ($logs as $log) {
            $day = Carbon::parse($log['timestamp'])->format('Y-m-d');
            $days[$day] = ($days[$day] ?? 0) + 1;
        }
        ksort($days);
        return $days;
    }

    /**
     * Group logs by hour.
     */
    private function groupByHour(array $logs): array
    {
        $hours = [];
        foreach ($logs as $log) {
            $hour = Carbon::parse($log['timestamp'])->format('Y-m-d H:00');
            $hours[$hour] = ($hours[$hour] ?? 0) + 1;
        }
        ksort($hours);
        return $hours;
    }

    /**
     * Get critical errors.
     */
    private function getCriticalErrors(array $logs): array
    {
        return array_filter($logs, fn($log) => in_array($log['level'], ['emergency', 'alert', 'critical']));
    }

    /**
     * Perform system health check.
     */
    private function performHealthCheck(array $analysis): array
    {
        $errorRate = $analysis['summary']['error_rate'];
        $criticalErrors = count(array_filter($analysis['level_breakdown'], 
            fn($count, $level) => in_array($level, ['emergency', 'alert', 'critical']), 
            ARRAY_FILTER_USE_BOTH));
        
        $healthScore = 100;
        $issues = [];
        
        // Deduct points for high error rate
        if ($errorRate > 10) {
            $healthScore -= 30;
            $issues[] = "High error rate: {$errorRate}%";
        } elseif ($errorRate > 5) {
            $healthScore -= 15;
            $issues[] = "Elevated error rate: {$errorRate}%";
        }
        
        // Deduct points for critical errors
        if ($criticalErrors > 10) {
            $healthScore -= 40;
            $issues[] = "{$criticalErrors} critical errors detected";
        } elseif ($criticalErrors > 0) {
            $healthScore -= 20;
            $issues[] = "{$criticalErrors} critical errors detected";
        }
        
        // Determine health status
        $status = match (true) {
            $healthScore >= 90 => 'excellent',
            $healthScore >= 70 => 'good',
            $healthScore >= 50 => 'fair',
            $healthScore >= 30 => 'poor',
            default => 'critical',
        };
        
        return [
            'score' => $healthScore,
            'status' => $status,
            'issues' => $issues,
            'recommendations' => $this->generateRecommendations($analysis),
        ];
    }

    /**
     * Generate recommendations based on analysis.
     */
    private function generateRecommendations(array $analysis): array
    {
        $recommendations = [];
        
        if ($analysis['summary']['error_rate'] > 5) {
            $recommendations[] = 'Consider reviewing and fixing the most common errors';
        }
        
        if (isset($analysis['error_patterns']['database_errors']) && $analysis['error_patterns']['database_errors'] > 10) {
            $recommendations[] = 'Review database queries and connections for optimization';
        }
        
        if (isset($analysis['error_patterns']['plugin_errors']) && $analysis['error_patterns']['plugin_errors'] > 5) {
            $recommendations[] = 'Check plugin compatibility and update problematic plugins';
        }
        
        return $recommendations;
    }

    /**
     * Analyze trends over time.
     */
    private function analyzeTrends(string $period): array
    {
        // For trend analysis, we need historical data
        // This is a simplified implementation
        return [
            'trend_direction' => 'stable', // Could be: increasing, decreasing, stable
            'growth_rate' => 0.0,
            'note' => 'Trend analysis requires longer historical data',
        ];
    }

    /**
     * Display analysis results.
     */
    private function displayAnalysis(array $analysis): void
    {
        $format = $this->option('format');
        
        match ($format) {
            'json' => $this->displayAsJson($analysis),
            'report' => $this->displayAsReport($analysis),
            default => $this->displayAsTable($analysis),
        };
    }

    /**
     * Display analysis as table format.
     */
    private function displayAsTable(array $analysis): void
    {
        $this->info("Error Analysis Report - Period: {$analysis['period']}");
        $this->info("Analysis Period: {$analysis['since']} to " . now()->toDateTimeString());
        $this->line('');

        // Summary
        $this->info('=== SUMMARY ===');
        $summary = $analysis['summary'];
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Log Entries', number_format($summary['total_entries'])],
                ['Errors', number_format($summary['errors'])],
                ['Warnings', number_format($summary['warnings'])],
                ['Error Rate', $summary['error_rate'] . '%'],
                ['Most Common Level', $summary['most_common_level']],
                ['Most Active Category', $summary['most_active_category']],
            ]
        );

        // Health Status
        if (isset($analysis['health_status'])) {
            $this->line('');
            $this->info('=== SYSTEM HEALTH ===');
            $health = $analysis['health_status'];
            $statusColor = match ($health['status']) {
                'excellent', 'good' => 'green',
                'fair' => 'yellow',
                'poor', 'critical' => 'red',
                default => 'white',
            };
            
            $this->line("<fg={$statusColor}>Health Score: {$health['score']}/100 ({$health['status']})</>");
            
            if (!empty($health['issues'])) {
                $this->line('Issues:');
                foreach ($health['issues'] as $issue) {
                    $this->line("  • {$issue}");
                }
            }
        }

        // Level Breakdown
        $this->line('');
        $this->info('=== ERROR LEVELS ===');
        $levelRows = [];
        foreach ($analysis['level_breakdown'] as $level => $count) {
            $levelRows[] = [ucfirst($level), number_format($count)];
        }
        $this->table(['Level', 'Count'], $levelRows);

        // Top Errors
        if (!empty($analysis['top_errors'])) {
            $this->line('');
            $this->info('=== TOP ERRORS ===');
            $errorRows = [];
            foreach ($analysis['top_errors'] as $error => $count) {
                $errorRows[] = [substr($error, 0, 80) . '...', $count];
            }
            $this->table(['Error Message', 'Count'], array_slice($errorRows, 0, 5));
        }
    }

    /**
     * Display analysis as JSON.
     */
    private function displayAsJson(array $analysis): void
    {
        $this->line(json_encode($analysis, JSON_PRETTY_PRINT));
    }

    /**
     * Display analysis as formatted report.
     */
    private function displayAsReport(array $analysis): void
    {
        $this->displayAsTable($analysis);
        
        if (isset($analysis['health_status']['recommendations'])) {
            $this->line('');
            $this->info('=== RECOMMENDATIONS ===');
            foreach ($analysis['health_status']['recommendations'] as $recommendation) {
                $this->line("• {$recommendation}");
            }
        }
    }

    /**
     * Export analysis to file.
     */
    private function exportAnalysis(array $analysis, string $path): void
    {
        $format = pathinfo($path, PATHINFO_EXTENSION) ?: 'json';
        
        $content = match ($format) {
            'txt' => $this->formatAsText($analysis),
            default => json_encode($analysis, JSON_PRETTY_PRINT),
        };

        File::put($path, $content);
        $this->info("Analysis exported to: {$path}");
    }

    /**
     * Format analysis as plain text.
     */
    private function formatAsText(array $analysis): string
    {
        $output = "Error Analysis Report - Period: {$analysis['period']}\n";
        $output .= "Generated: " . now()->toDateTimeString() . "\n\n";
        
        $output .= "=== SUMMARY ===\n";
        foreach ($analysis['summary'] as $key => $value) {
            $output .= ucfirst(str_replace('_', ' ', $key)) . ": {$value}\n";
        }
        
        return $output;
    }

    /**
     * Get most common item from array.
     */
    private function getMostCommon(array $items): string
    {
        if (empty($items)) {
            return 'none';
        }
        
        $counts = array_count_values($items);
        arsort($counts);
        
        return array_key_first($counts);
    }

    /**
     * Normalize error message for pattern detection.
     */
    private function normalizeErrorMessage(string $message): string
    {
        // Remove line numbers, IDs, timestamps, etc.
        $normalized = preg_replace('/\d+/', 'N', $message);
        $normalized = preg_replace('/[a-f0-9]{32,}/', 'HASH', $normalized);
        $normalized = preg_replace('/\d{4}-\d{2}-\d{2}/', 'DATE', $normalized);
        
        return substr($normalized, 0, 100); // Limit length
    }
}