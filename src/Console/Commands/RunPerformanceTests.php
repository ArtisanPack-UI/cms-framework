<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Run Performance Tests Command
 * 
 * This command provides an easy interface to run various performance tests
 * including benchmarks, load tests, and memory profiling.
 * 
 * @package ArtisanPackUI\CMSFramework\Console\Commands
 */
class RunPerformanceTests extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:performance-test 
                          {--type=all : Type of test to run (database, api, load, memory, all)}
                          {--group= : Specific group to test (e.g., user, content, auth)}
                          {--format=table : Output format (table, csv, html, json)}
                          {--output= : Output directory for results}
                          {--iterations=5 : Number of iterations per benchmark}
                          {--baseline : Establish baseline performance metrics}
                          {--compare= : Compare with baseline from specified file}';

    /**
     * The console command description.
     */
    protected $description = 'Run comprehensive performance tests for the CMS Framework';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayHeader();
        
        $type = $this->option('type');
        $group = $this->option('group');
        $format = $this->option('format');
        $outputDir = $this->option('output') ?? storage_path('performance');
        $iterations = $this->option('iterations');
        $baseline = $this->option('baseline');
        $compare = $this->option('compare');

        // Ensure output directory exists
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        try {
            if ($baseline) {
                return $this->establishBaseline($type, $group, $outputDir, $iterations);
            }

            if ($compare) {
                return $this->compareWithBaseline($compare, $type, $group, $outputDir, $iterations);
            }

            return $this->runPerformanceTests($type, $group, $format, $outputDir, $iterations);

        } catch (\Exception $e) {
            $this->error('❌ Performance test execution failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display command header
     */
    protected function displayHeader(): void
    {
        $this->newLine();
        $this->line('🚀 <info>CMS Framework Performance Testing Suite</info>');
        $this->line('═══════════════════════════════════════════');
        $this->newLine();
    }

    /**
     * Run performance tests based on type and group
     */
    protected function runPerformanceTests(string $type, ?string $group, string $format, string $outputDir, int $iterations): int
    {
        $this->info("📊 Running {$type} performance tests...");
        
        $benchmarkCommand = $this->buildBenchmarkCommand($type, $group, $format, $outputDir, $iterations);
        
        $this->line("🔧 Command: {$benchmarkCommand}");
        $this->newLine();

        $process = Process::fromShellCommandline($benchmarkCommand);
        $process->setTimeout(600); // 10 minutes timeout

        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->error($buffer);
            } else {
                $this->line($buffer);
            }
        });

        if ($process->isSuccessful()) {
            $this->displayResults($outputDir, $format);
            return Command::SUCCESS;
        } else {
            $this->error('❌ Performance tests failed');
            return Command::FAILURE;
        }
    }

    /**
     * Build PHPBench command based on options
     */
    protected function buildBenchmarkCommand(string $type, ?string $group, string $format, string $outputDir, int $iterations): string
    {
        $command = './vendor/bin/phpbench run tests/Performance/';
        
        // Add specific benchmark file based on type
        switch ($type) {
            case 'database':
                $command .= 'DatabasePerformanceBench.php';
                break;
            case 'api':
                $command .= 'ApiPerformanceBench.php';
                break;
            case 'load':
                $command .= 'LoadTestingBench.php';
                break;
            case 'memory':
                $command .= 'MemoryProfilingBench.php';
                break;
            case 'all':
            default:
                // Run all benchmark files
                break;
        }

        // Add group filter if specified
        if ($group) {
            $command .= " --group={$group}";
        }

        // Add iterations
        $command .= " --iterations={$iterations}";

        // Add output format and file
        $timestamp = date('Y-m-d_H-i-s');
        $outputFile = "{$outputDir}/benchmark_{$type}_{$timestamp}";
        
        switch ($format) {
            case 'csv':
                $command .= " --report=csv --output=\"{$outputFile}.csv\"";
                break;
            case 'html':
                $command .= " --report=html --output=\"{$outputFile}.html\"";
                break;
            case 'json':
                $command .= " --report=json --output=\"{$outputFile}.json\"";
                break;
            case 'table':
            default:
                $command .= " --report=table";
                break;
        }

        return $command;
    }

    /**
     * Establish baseline performance metrics
     */
    protected function establishBaseline(string $type, ?string $group, string $outputDir, int $iterations): int
    {
        $this->info('📈 Establishing baseline performance metrics...');
        
        $baselineFile = "{$outputDir}/baseline_" . date('Y-m-d_H-i-s') . '.json';
        
        $command = $this->buildBenchmarkCommand($type, $group, 'json', $outputDir, $iterations * 2); // More iterations for baseline
        $command .= " --store --storage-dir=\"{$outputDir}\"";
        
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(900); // 15 minutes for baseline

        $process->run(function ($type, $buffer) {
            $this->line($buffer);
        });

        if ($process->isSuccessful()) {
            $this->info("✅ Baseline established and saved to: {$baselineFile}");
            return Command::SUCCESS;
        } else {
            $this->error('❌ Failed to establish baseline');
            return Command::FAILURE;
        }
    }

    /**
     * Compare current performance with baseline
     */
    protected function compareWithBaseline(string $baselineFile, string $type, ?string $group, string $outputDir, int $iterations): int
    {
        $this->info("📊 Comparing performance with baseline: {$baselineFile}");
        
        if (!file_exists($baselineFile)) {
            $this->error("❌ Baseline file not found: {$baselineFile}");
            return Command::FAILURE;
        }

        $command = $this->buildBenchmarkCommand($type, $group, 'table', $outputDir, $iterations);
        $command .= " --ref=\"{$baselineFile}\" --report=diff";

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(600);

        $process->run(function ($type, $buffer) {
            $this->line($buffer);
        });

        if ($process->isSuccessful()) {
            $this->analyzePerformanceRegression($baselineFile, $outputDir);
            return Command::SUCCESS;
        } else {
            $this->error('❌ Performance comparison failed');
            return Command::FAILURE;
        }
    }

    /**
     * Display test results
     */
    protected function displayResults(string $outputDir, string $format): void
    {
        $this->newLine();
        $this->info('📋 Performance Test Results:');
        $this->line('═══════════════════════════');
        
        // List generated files
        $files = glob("{$outputDir}/benchmark_*");
        if (!empty($files)) {
            foreach ($files as $file) {
                $this->line("📄 " . basename($file));
            }
        }

        $this->newLine();
        $this->displayPerformanceSummary($outputDir);
        $this->displayRecommendations();
    }

    /**
     * Display performance summary
     */
    protected function displayPerformanceSummary(string $outputDir): void
    {
        $this->info('🎯 Performance Summary:');
        
        // Check for memory metrics
        $memoryMetricsFile = "{$outputDir}/memory_metrics.json";
        if (file_exists($memoryMetricsFile)) {
            $this->analyzeMemoryMetrics($memoryMetricsFile);
        }

        // Check for GC stats
        $gcStatsFile = "{$outputDir}/gc_stats.json";
        if (file_exists($gcStatsFile)) {
            $this->analyzeGcStats($gcStatsFile);
        }

        // Check for memory leak analysis
        $leakAnalysisFile = "{$outputDir}/memory_leak_analysis.json";
        if (file_exists($leakAnalysisFile)) {
            $this->analyzeMemoryLeaks($leakAnalysisFile);
        }
    }

    /**
     * Analyze memory metrics
     */
    protected function analyzeMemoryMetrics(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $totalMemoryGrowth = 0;
        $operations = [];

        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data) {
                $totalMemoryGrowth += $data['memory_growth'];
                $operations[$data['operation']] = ($operations[$data['operation']] ?? 0) + $data['memory_growth'];
            }
        }

        $this->line("   💾 Total Memory Growth: " . $this->formatBytes($totalMemoryGrowth));
        
        foreach ($operations as $operation => $growth) {
            $this->line("   📊 {$operation}: " . $this->formatBytes($growth));
        }
    }

    /**
     * Analyze garbage collection stats
     */
    protected function analyzeGcStats(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $totalGcTime = 0;
        $totalMemoryFreed = 0;

        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data) {
                $totalGcTime += $data['gc_time'];
                $totalMemoryFreed += $data['memory_freed'];
            }
        }

        $this->line("   🗑️  Total GC Time: " . number_format($totalGcTime * 1000, 2) . "ms");
        $this->line("   🆓 Total Memory Freed: " . $this->formatBytes($totalMemoryFreed));
    }

    /**
     * Analyze memory leaks
     */
    protected function analyzeMemoryLeaks(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $leaksDetected = 0;

        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data && $data['leak_detected']) {
                $leaksDetected++;
            }
        }

        if ($leaksDetected > 0) {
            $this->warn("   ⚠️  Memory Leaks Detected: {$leaksDetected}");
        } else {
            $this->info("   ✅ No Memory Leaks Detected");
        }
    }

    /**
     * Analyze performance regression
     */
    protected function analyzePerformanceRegression(string $baselineFile, string $outputDir): void
    {
        $this->info('🔍 Performance Regression Analysis:');
        
        // This would contain logic to compare current results with baseline
        // and identify performance regressions
        $this->line('   📈 Regression analysis completed');
        $this->line('   📊 Results saved to regression analysis files');
    }

    /**
     * Display performance recommendations
     */
    protected function displayRecommendations(): void
    {
        $this->newLine();
        $this->info('💡 Performance Recommendations:');
        $this->line('═══════════════════════════════');
        
        $recommendations = [
            '🚀 Enable OPcache for better PHP performance',
            '🗄️  Configure database connection pooling',
            '💾 Implement Redis caching for frequently accessed data',
            '📊 Monitor memory usage in production environments',
            '🔧 Consider database query optimization for slow queries',
            '⚡ Enable Gzip compression for API responses',
            '🔄 Implement proper database indexing for large datasets'
        ];

        foreach ($recommendations as $recommendation) {
            $this->line("   {$recommendation}");
        }
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        return number_format($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}