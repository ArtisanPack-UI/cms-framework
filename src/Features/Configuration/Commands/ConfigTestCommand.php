<?php

namespace ArtisanPackUI\CMSFramework\Features\Configuration\Commands;

use Illuminate\Console\Command;
use ArtisanPackUI\CMSFramework\Features\Configuration\Validation\ConfigurationValidator;
use ArtisanPackUI\CMSFramework\Features\Configuration\Validation\ValidationResult;

/**
 * Configuration Test Command
 * 
 * This command validates configuration files against their schemas
 * and provides detailed reporting of any validation errors.
 */
class ConfigTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:config:test 
                           {config? : Specific configuration to test (e.g., cms, cache)}
                           {--environment : Include environment variable validation}
                           {--format=table : Output format (table, json, detailed)}
                           {--fix : Attempt to fix common configuration issues}
                           {--export= : Export results to file}';

    /**
     * The console command description.
     */
    protected $description = 'Test and validate CMS configuration files';

    /**
     * Configuration validator instance
     */
    protected ConfigurationValidator $validator;

    /**
     * Create a new command instance.
     */
    public function __construct(ConfigurationValidator $validator)
    {
        parent::__construct();
        $this->validator = $validator;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('🔍 Testing CMS configuration...');
            
            $specificConfig = $this->argument('config');
            $includeEnvironment = $this->option('environment');
            $format = $this->option('format');
            
            // Perform validation
            $results = $this->performValidation($specificConfig, $includeEnvironment);
            
            // Display results
            $this->displayResults($results, $format);
            
            // Attempt fixes if requested
            if ($this->option('fix')) {
                $this->attemptFixes($results);
            }
            
            // Export results if requested
            if ($exportFile = $this->option('export')) {
                $this->exportResults($results, $exportFile);
            }
            
            // Return appropriate exit code
            return $this->getExitCode($results);
            
        } catch (\Exception $e) {
            $this->error('❌ Configuration testing failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Perform configuration validation
     */
    protected function performValidation(?string $specificConfig, bool $includeEnvironment): array
    {
        $results = [];
        
        if ($specificConfig) {
            // Test specific configuration
            $this->info("Testing configuration: {$specificConfig}");
            $results[$specificConfig] = $this->validator->validate($specificConfig);
        } else {
            // Test all configurations
            $this->info('Testing all registered configurations...');
            $results = $this->validator->validateAll();
        }
        
        // Test environment if requested
        if ($includeEnvironment) {
            $this->info('Testing environment variables...');
            $results['environment'] = $this->validator->validateEnvironment();
        }
        
        return $results;
    }
    
    /**
     * Display validation results
     */
    protected function displayResults(array $results, string $format): void
    {
        switch ($format) {
            case 'json':
                $this->displayJsonResults($results);
                break;
            case 'detailed':
                $this->displayDetailedResults($results);
                break;
            case 'table':
            default:
                $this->displayTableResults($results);
                break;
        }
    }
    
    /**
     * Display results in table format
     */
    protected function displayTableResults(array $results): void
    {
        $tableData = [];
        $totalErrors = 0;
        $totalConfigs = count($results);
        $validConfigs = 0;
        
        foreach ($results as $configKey => $result) {
            $status = $result->isValid() ? '✅ Valid' : '❌ Invalid';
            $errorCount = $result->getErrorCount();
            $totalErrors += $errorCount;
            
            if ($result->isValid()) {
                $validConfigs++;
            }
            
            $tableData[] = [
                $configKey,
                $status,
                $errorCount,
                $result->isValid() ? '-' : $result->getFirstError(),
            ];
        }
        
        $this->table(
            ['Configuration', 'Status', 'Errors', 'First Error'],
            $tableData
        );
        
        // Summary
        $this->info("\n📊 Summary:");
        $this->info("Total configurations: {$totalConfigs}");
        $this->info("Valid configurations: {$validConfigs}");
        $this->info("Invalid configurations: " . ($totalConfigs - $validConfigs));
        $this->info("Total errors: {$totalErrors}");
        
        if ($totalErrors === 0) {
            $this->info("\n🎉 All configurations are valid!");
        }
    }
    
    /**
     * Display results in detailed format
     */
    protected function displayDetailedResults(array $results): void
    {
        foreach ($results as $configKey => $result) {
            $this->info("\n" . str_repeat('=', 50));
            $this->info("Configuration: {$configKey}");
            $this->info(str_repeat('=', 50));
            
            if ($result->isValid()) {
                $this->info("✅ Configuration is valid");
            } else {
                $this->error("❌ Configuration has errors:");
                $this->line($result->getFormattedReport());
            }
        }
        
        // Overall summary
        $summary = $this->validator->getValidationSummary();
        $this->info("\n" . str_repeat('=', 50));
        $this->info("OVERALL SUMMARY");
        $this->info(str_repeat('=', 50));
        $this->info("Total configurations: {$summary['total_configurations']}");
        $this->info("Valid: {$summary['valid_configurations']}");
        $this->info("Invalid: {$summary['invalid_configurations']}");
        $this->info("Total errors: {$summary['total_errors']}");
        $this->info("Environment valid: " . ($summary['environment_valid'] ? 'Yes' : 'No'));
    }
    
    /**
     * Display results in JSON format
     */
    protected function displayJsonResults(array $results): void
    {
        $jsonData = [];
        
        foreach ($results as $configKey => $result) {
            $jsonData[$configKey] = $result->toArray();
        }
        
        $this->line(json_encode($jsonData, JSON_PRETTY_PRINT));
    }
    
    /**
     * Attempt to fix common configuration issues
     */
    protected function attemptFixes(array $results): void
    {
        $this->info("\n🔧 Attempting to fix common configuration issues...");
        
        $fixesApplied = 0;
        
        foreach ($results as $configKey => $result) {
            if ($result->failed()) {
                $fixes = $this->generateFixes($configKey, $result);
                
                foreach ($fixes as $fix) {
                    if ($this->confirm("Apply fix: {$fix['description']}?", true)) {
                        try {
                            $fix['action']();
                            $this->info("✅ Applied: {$fix['description']}");
                            $fixesApplied++;
                        } catch (\Exception $e) {
                            $this->error("❌ Failed to apply: {$fix['description']} - " . $e->getMessage());
                        }
                    }
                }
            }
        }
        
        if ($fixesApplied > 0) {
            $this->info("\n🎉 Applied {$fixesApplied} fix(es). Re-run the test to verify.");
        } else {
            $this->warn("\n⚠️  No fixes were applied.");
        }
    }
    
    /**
     * Generate possible fixes for configuration issues
     */
    protected function generateFixes(string $configKey, ValidationResult $result): array
    {
        $fixes = [];
        
        // Generate fixes based on common error patterns
        foreach ($result->getErrors() as $error) {
            if (str_contains($error, 'Theme directory does not exist')) {
                $fixes[] = [
                    'description' => 'Create missing theme directory',
                    'action' => function() use ($configKey) {
                        $themeName = config("{$configKey}.theme.active");
                        $themePath = base_path("themes/{$themeName}");
                        if (!file_exists($themePath)) {
                            mkdir($themePath, 0755, true);
                        }
                    }
                ];
            }
            
            if (str_contains($error, 'APP_KEY is required')) {
                $fixes[] = [
                    'description' => 'Generate missing APP_KEY',
                    'action' => function() {
                        $this->call('key:generate');
                    }
                ];
            }
            
            if (str_contains($error, 'Media disk') && str_contains($error, 'not configured')) {
                $fixes[] = [
                    'description' => 'Add missing media disk configuration',
                    'action' => function() {
                        $this->warn('Please manually add the missing disk configuration to config/filesystems.php');
                    }
                ];
            }
        }
        
        return $fixes;
    }
    
    /**
     * Export results to file
     */
    protected function exportResults(array $results, string $filename): void
    {
        try {
            $exportData = [
                'timestamp' => now()->toISOString(),
                'summary' => $this->validator->getValidationSummary(),
                'results' => [],
            ];
            
            foreach ($results as $configKey => $result) {
                $exportData['results'][$configKey] = $result->toArray();
            }
            
            $content = json_encode($exportData, JSON_PRETTY_PRINT);
            file_put_contents($filename, $content);
            
            $this->info("📄 Results exported to: {$filename}");
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to export results: " . $e->getMessage());
        }
    }
    
    /**
     * Get appropriate exit code based on results
     */
    protected function getExitCode(array $results): int
    {
        foreach ($results as $result) {
            if ($result->failed()) {
                return Command::FAILURE;
            }
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Display helpful tips for configuration management
     */
    protected function displayTips(): void
    {
        $this->info("\n💡 Configuration Tips:");
        $this->line("• Run 'php artisan cms:config:test --environment' to include environment validation");
        $this->line("• Use '--format=detailed' for comprehensive error information");
        $this->line("• Use '--fix' to automatically resolve common issues");
        $this->line("• Export results with '--export=filename.json' for documentation");
        $this->line("• Test specific configurations with 'php artisan cms:config:test cms'");
    }
}