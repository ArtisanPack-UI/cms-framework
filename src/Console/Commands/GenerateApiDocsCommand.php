<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use Illuminate\Console\Command;
use L5Swagger\L5SwaggerFacade;

/**
 * Generate API Documentation Command
 *
 * Generates OpenAPI/Swagger documentation for the CMS Framework API.
 *
 * @package ArtisanPackUI\CMSFramework\Console\Commands
 */
class GenerateApiDocsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:generate-api-docs 
                            {--force : Force regeneration even if docs exist}
                            {--format=json : Output format (json, yaml)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate comprehensive API documentation for the CMS Framework';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('🚀 Generating CMS Framework API Documentation...');
        
        try {
            // Set the format based on option
            $format = $this->option('format');
            if (!in_array($format, ['json', 'yaml'])) {
                $this->error('Invalid format. Use json or yaml.');
                return self::FAILURE;
            }

            // Configure L5Swagger to generate documentation
            config(['l5-swagger.defaults.generate_always' => true]);
            config(['l5-swagger.defaults.generate_yaml_copy' => $format === 'yaml']);

            // Generate the documentation
            $this->info('📝 Scanning source code for OpenAPI annotations...');
            
            // Generate documentation using L5Swagger
            L5SwaggerFacade::generate('default');
            
            $this->info('✅ API Documentation generated successfully!');
            $this->newLine();
            
            // Display information about generated files
            $docsPath = storage_path('api-docs');
            $jsonFile = $docsPath . '/api-docs.json';
            $yamlFile = $docsPath . '/api-docs.yaml';
            
            if (file_exists($jsonFile)) {
                $this->info("📄 JSON documentation: {$jsonFile}");
                $this->info("📊 File size: " . $this->formatBytes(filesize($jsonFile)));
            }
            
            if ($format === 'yaml' && file_exists($yamlFile)) {
                $this->info("📄 YAML documentation: {$yamlFile}");
                $this->info("📊 File size: " . $this->formatBytes(filesize($yamlFile)));
            }
            
            $this->newLine();
            $this->info('🌐 To view the interactive documentation:');
            $this->info('   Visit: /api/documentation (when served in a Laravel app)');
            $this->newLine();
            
            // Summary information
            $this->displaySummary($jsonFile);
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to generate API documentation:');
            $this->error($e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }
            
            return self::FAILURE;
        }
    }

    /**
     * Display summary information about the generated documentation
     *
     * @param string $jsonFile
     * @return void
     */
    protected function displaySummary(string $jsonFile): void
    {
        if (!file_exists($jsonFile)) {
            return;
        }

        try {
            $content = json_decode(file_get_contents($jsonFile), true);
            
            if (!$content || !isset($content['paths'])) {
                return;
            }

            $pathCount = count($content['paths']);
            $operationCount = 0;
            $tagCounts = [];

            // Count operations and organize by tags
            foreach ($content['paths'] as $path => $methods) {
                foreach ($methods as $method => $operation) {
                    if (in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'options', 'head'])) {
                        $operationCount++;
                        
                        if (isset($operation['tags'])) {
                            foreach ($operation['tags'] as $tag) {
                                $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                            }
                        }
                    }
                }
            }

            $this->info('📈 Documentation Summary:');
            $this->info("   • API Paths: {$pathCount}");
            $this->info("   • Total Operations: {$operationCount}");
            
            if (!empty($tagCounts)) {
                $this->info('   • Operations by Category:');
                foreach ($tagCounts as $tag => $count) {
                    $this->info("     - {$tag}: {$count} operations");
                }
            }

            // Check for schemas
            if (isset($content['components']['schemas'])) {
                $schemaCount = count($content['components']['schemas']);
                $this->info("   • Data Schemas: {$schemaCount}");
            }

        } catch (\Exception $e) {
            // Silent fail for summary - not critical
        }
    }

    /**
     * Format bytes into human readable format
     *
     * @param int $size
     * @return string
     */
    protected function formatBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }
        
        return round($size, 2) . ' ' . $units[$unitIndex];
    }
}