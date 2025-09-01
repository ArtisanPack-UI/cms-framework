<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generate API Documentation Command.
 *
 * Generates comprehensive OpenAPI/Swagger documentation for the CMS Framework API.
 * This command scans all controllers and generates both JSON and YAML documentation files.
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Console\Commands
 * @since      1.1.0
 */
class GenerateApiDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:generate-api-docs
                          {--format=json : The format for the documentation (json, yaml, both)}
                          {--output= : Custom output directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate comprehensive OpenAPI/Swagger documentation for the CMS Framework API';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('🚀 Generating CMS Framework API Documentation...');

        $format = $this->option('format');
        $customOutput = $this->option('output');

        // Generate the documentation using l5-swagger
        try {
            if ($format === 'both' || $format === 'json') {
                $this->call('l5-swagger:generate', ['--format' => 'json']);
                $this->line('✅ JSON documentation generated successfully');
            }

            if ($format === 'both' || $format === 'yaml') {
                $this->call('l5-swagger:generate', ['--format' => 'yaml']);
                $this->line('✅ YAML documentation generated successfully');
            }

            // Display documentation stats
            $this->displayDocumentationStats();

            $this->newLine();
            $this->info('📚 API Documentation generated successfully!');
            $this->info('🌐 View documentation at: /api/documentation');
            $this->info('📄 JSON file location: storage/api-docs/api-docs.json');
            $this->info('📄 YAML file location: storage/api-docs/api-docs.yaml');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to generate API documentation: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display documentation statistics.
     *
     * @return void
     */
    protected function displayDocumentationStats(): void
    {
        $this->newLine();
        $this->line('📊 Documentation Statistics:');
        
        // Count controllers with OpenAPI annotations
        $controllersPath = base_path('src/Http/Controllers');
        $controllers = glob($controllersPath . '/*.php');
        $documentedControllers = 0;
        $totalEndpoints = 0;

        foreach ($controllers as $controller) {
            $content = file_get_contents($controller);
            if (strpos($content, '#[OA\\') !== false) {
                $documentedControllers++;
                // Count OpenAPI endpoint annotations
                $totalEndpoints += substr_count($content, '#[OA\Get(');
                $totalEndpoints += substr_count($content, '#[OA\Post(');
                $totalEndpoints += substr_count($content, '#[OA\Put(');
                $totalEndpoints += substr_count($content, '#[OA\Delete(');
                $totalEndpoints += substr_count($content, '#[OA\Patch(');
            }
        }

        $this->line("   📁 Controllers documented: {$documentedControllers}/" . count($controllers));
        $this->line("   🔗 API endpoints documented: {$totalEndpoints}");
        
        // List documented tags/categories
        $tags = [
            'Authentication' => '🔐',
            'Content Management' => '📝', 
            'User Management' => '👥',
            'Media Management' => '🖼️',
            'System Management' => '⚙️',
            'Plugin Management' => '🔌',
            'Taxonomy Management' => '🏷️'
        ];

        $this->line('   📋 API Categories:');
        foreach ($tags as $tag => $icon) {
            $this->line("      {$icon} {$tag}");
        }
    }
}