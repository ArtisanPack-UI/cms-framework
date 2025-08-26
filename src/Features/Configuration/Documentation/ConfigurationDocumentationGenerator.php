<?php

namespace ArtisanPackUI\CMSFramework\Features\Configuration\Documentation;

use Illuminate\Support\Facades\File;
use ArtisanPackUI\CMSFramework\Features\Configuration\Schemas\CmsConfigSchema;

/**
 * Configuration Documentation Generator
 * 
 * Automatically generates comprehensive documentation from configuration schemas
 * including examples, descriptions, and validation rules in markdown format.
 */
class ConfigurationDocumentationGenerator
{
    /**
     * Available schema classes for documentation generation
     */
    protected array $schemaClasses = [
        'cms' => CmsConfigSchema::class,
        // Additional schema classes will be added here
    ];
    
    /**
     * Generate documentation for all configuration schemas
     */
    public function generateAll(string $outputDirectory = null): array
    {
        $outputDirectory = $outputDirectory ?? base_path('docs/configuration');
        File::ensureDirectoryExists($outputDirectory);
        
        $results = [];
        
        foreach ($this->schemaClasses as $configKey => $schemaClass) {
            $results[$configKey] = $this->generateForSchema($configKey, $schemaClass, $outputDirectory);
        }
        
        // Generate index file
        $indexFile = $this->generateIndex($outputDirectory, $results);
        $results['index'] = $indexFile;
        
        return $results;
    }
    
    /**
     * Generate documentation for a specific schema
     */
    public function generateForSchema(string $configKey, string $schemaClass, string $outputDirectory): array
    {
        $documentation = $schemaClass::getDocumentation();
        $schema = $schemaClass::getSchema();
        
        $markdown = $this->generateMarkdown($configKey, $documentation, $schema, $schemaClass);
        
        $filename = "{$configKey}-configuration.md";
        $filepath = "{$outputDirectory}/{$filename}";
        
        File::put($filepath, $markdown);
        
        return [
            'config' => $configKey,
            'schema_class' => $schemaClass,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => strlen($markdown),
            'sections' => count($schema),
        ];
    }
    
    /**
     * Generate markdown documentation
     */
    protected function generateMarkdown(string $configKey, array $documentation, array $schema, string $schemaClass): string
    {
        $markdown = '';
        
        // Header
        $markdown .= "# {$documentation['title']}\n\n";
        $markdown .= "> {$documentation['description']}\n\n";
        $markdown .= "**Version:** {$documentation['version']}  \n";
        $markdown .= "**Schema Class:** `{$schemaClass}`  \n";
        $markdown .= "**Generated:** " . now()->format('Y-m-d H:i:s') . "\n\n";
        
        // Table of Contents
        $markdown .= "## Table of Contents\n\n";
        foreach ($schema as $sectionName => $sectionConfig) {
            $markdown .= "- [" . ucfirst(str_replace('_', ' ', $sectionName)) . "](#" . str_replace('_', '-', $sectionName) . ")\n";
        }
        $markdown .= "\n";
        
        // Overview
        $markdown .= "## Overview\n\n";
        $markdown .= "This configuration file defines settings for the CMS framework. ";
        $markdown .= "All configuration values are validated against the schema to ensure correctness and prevent runtime errors.\n\n";
        
        // Configuration Structure
        $markdown .= "## Configuration Structure\n\n";
        $markdown .= "```php\n";
        $markdown .= "return [\n";
        foreach ($schema as $sectionName => $sectionConfig) {
            $markdown .= "    '{$sectionName}' => [\n";
            $markdown .= "        // {$sectionConfig['description']}\n";
            $markdown .= "    ],\n";
        }
        $markdown .= "];\n";
        $markdown .= "```\n\n";
        
        // Detailed Sections
        foreach ($schema as $sectionName => $sectionConfig) {
            $markdown .= $this->generateSectionDocumentation($sectionName, $sectionConfig, $documentation);
        }
        
        // Validation Rules Summary
        $markdown .= $this->generateValidationSummary($schemaClass);
        
        // Examples
        if (isset($documentation['examples'])) {
            $markdown .= $this->generateExamples($documentation['examples']);
        }
        
        // Footer
        $markdown .= "---\n\n";
        $markdown .= "*This documentation was automatically generated from the configuration schema.*\n";
        
        return $markdown;
    }
    
    /**
     * Generate documentation for a configuration section
     */
    protected function generateSectionDocumentation(string $sectionName, array $sectionConfig, array $documentation): string
    {
        $markdown = '';
        
        $markdown .= "## " . ucfirst(str_replace('_', ' ', $sectionName)) . "\n\n";
        $markdown .= "**Type:** `{$sectionConfig['type']}`  \n";
        $markdown .= "**Required:** " . ($sectionConfig['required'] ? 'Yes' : 'No') . "  \n";
        $markdown .= "**Description:** {$sectionConfig['description']}\n\n";
        
        // Validation Rules Table
        if (isset($sectionConfig['rules'])) {
            $markdown .= "### Validation Rules\n\n";
            $markdown .= "| Field | Rules | Description |\n";
            $markdown .= "|-------|-------|-------------|\n";
            
            foreach ($sectionConfig['rules'] as $field => $rules) {
                $fieldName = str_replace($sectionName . '.', '', $field);
                $rulesStr = $this->formatRules($rules);
                $description = $this->getFieldDescription($field, $sectionName);
                $markdown .= "| `{$fieldName}` | `{$rulesStr}` | {$description} |\n";
            }
            $markdown .= "\n";
        }
        
        // Example Configuration
        if (isset($documentation['examples'][$sectionName])) {
            $markdown .= "### Example Configuration\n\n";
            $markdown .= "```php\n";
            $markdown .= "'{$sectionName}' => " . $this->arrayToPhpString($documentation['examples'][$sectionName], 1) . ",\n";
            $markdown .= "```\n\n";
        }
        
        return $markdown;
    }
    
    /**
     * Generate validation rules summary
     */
    protected function generateValidationSummary(string $schemaClass): string
    {
        $markdown = '';
        
        $markdown .= "## Validation Rules Summary\n\n";
        $markdown .= "The following validation rules are applied to ensure configuration correctness:\n\n";
        
        $rules = $schemaClass::getRules();
        $messages = $schemaClass::getMessages();
        
        $markdown .= "### All Validation Rules\n\n";
        $markdown .= "| Configuration Key | Validation Rules |\n";
        $markdown .= "|-------------------|------------------|\n";
        
        foreach ($rules as $field => $fieldRules) {
            $rulesStr = $this->formatRules($fieldRules);
            $markdown .= "| `{$field}` | `{$rulesStr}` |\n";
        }
        $markdown .= "\n";
        
        // Custom Messages
        if (!empty($messages)) {
            $markdown .= "### Custom Validation Messages\n\n";
            $markdown .= "The schema includes custom validation messages for better error reporting:\n\n";
            $markdown .= "| Rule | Custom Message |\n";
            $markdown .= "|------|----------------|\n";
            
            foreach ($messages as $key => $message) {
                $markdown .= "| `{$key}` | {$message} |\n";
            }
            $markdown .= "\n";
        }
        
        return $markdown;
    }
    
    /**
     * Generate examples section
     */
    protected function generateExamples(array $examples): string
    {
        $markdown = '';
        
        $markdown .= "## Configuration Examples\n\n";
        $markdown .= "Here are some complete configuration examples:\n\n";
        
        foreach ($examples as $name => $example) {
            $markdown .= "### " . ucfirst(str_replace('_', ' ', $name)) . " Example\n\n";
            $markdown .= "```php\n";
            $markdown .= "'{$name}' => " . $this->arrayToPhpString($example, 1) . ",\n";
            $markdown .= "```\n\n";
        }
        
        return $markdown;
    }
    
    /**
     * Generate index documentation file
     */
    protected function generateIndex(string $outputDirectory, array $results): array
    {
        $markdown = '';
        
        $markdown .= "# Configuration Documentation\n\n";
        $markdown .= "This directory contains automatically generated documentation for all CMS configuration files.\n\n";
        $markdown .= "**Generated:** " . now()->format('Y-m-d H:i:s') . "\n\n";
        
        $markdown .= "## Available Configuration Files\n\n";
        
        foreach ($results as $configKey => $result) {
            if ($configKey === 'index') continue;
            
            $markdown .= "### [{$result['config']} Configuration]({$result['filename']})\n\n";
            $markdown .= "- **Schema Class:** `{$result['schema_class']}`\n";
            $markdown .= "- **Sections:** {$result['sections']}\n";
            $markdown .= "- **File Size:** " . $this->formatBytes($result['size']) . "\n\n";
        }
        
        $markdown .= "## Usage\n\n";
        $markdown .= "Each configuration file includes:\n\n";
        $markdown .= "- Complete schema documentation with validation rules\n";
        $markdown .= "- Field descriptions and requirements\n";
        $markdown .= "- Configuration examples\n";
        $markdown .= "- Validation error messages\n\n";
        
        $markdown .= "## Validation\n\n";
        $markdown .= "All configurations are validated using the CMS configuration validation system. ";
        $markdown .= "You can test your configuration using:\n\n";
        $markdown .= "```bash\n";
        $markdown .= "php artisan cms:config:test\n";
        $markdown .= "```\n\n";
        
        $indexFile = "{$outputDirectory}/README.md";
        File::put($indexFile, $markdown);
        
        return [
            'filename' => 'README.md',
            'filepath' => $indexFile,
            'size' => strlen($markdown),
        ];
    }
    
    /**
     * Format validation rules for display
     */
    protected function formatRules(string $rules): string
    {
        // Replace pipe separators with commas for better readability
        return str_replace('|', ', ', $rules);
    }
    
    /**
     * Get field description based on field name
     */
    protected function getFieldDescription(string $field, string $section): string
    {
        $descriptions = [
            // Site section
            'site.name' => 'The name of the website or application',
            'site.tagline' => 'A brief description or tagline for the site',
            'site.url' => 'The primary URL of the website',
            'site.timezone' => 'Default timezone for the application',
            'site.locale' => 'Default language locale (ISO 639-1)',
            
            // Paths section
            'paths.plugins' => 'Directory path where plugins are stored',
            'paths.themes' => 'Directory path where themes are stored',
            
            // Media section
            'media.disk' => 'Storage disk for media files',
            'media.directory' => 'Directory within the disk for media storage',
            
            // Content types
            'content_types.*.label' => 'Display name for the content type',
            'content_types.*.slug' => 'URL-friendly identifier for the content type',
            'content_types.*.public' => 'Whether the content type is publicly accessible',
            'content_types.*.hierarchical' => 'Whether content can have parent-child relationships',
            'content_types.*.supports' => 'Array of features supported by this content type',
            
            // Taxonomies
            'taxonomies.*.label' => 'Display name for the taxonomy',
            'taxonomies.*.hierarchical' => 'Whether taxonomy terms can have parent-child relationships',
            'taxonomies.*.content_types' => 'Content types this taxonomy applies to',
            
            // Theme
            'theme.active' => 'The currently active theme identifier',
            
            // Rate limiting
            'rate_limiting.enabled' => 'Whether rate limiting is enabled globally',
            'rate_limiting.*.requests_per_minute' => 'Maximum number of requests per minute',
            'rate_limiting.*.key_generator' => 'Method used to generate rate limit keys',
        ];
        
        return $descriptions[$field] ?? 'Configuration field';
    }
    
    /**
     * Convert array to PHP string representation
     */
    protected function arrayToPhpString(array $array, int $depth = 0): string
    {
        $indent = str_repeat('    ', $depth);
        $nextIndent = str_repeat('    ', $depth + 1);
        
        if (empty($array)) {
            return '[]';
        }
        
        $elements = [];
        foreach ($array as $key => $value) {
            $keyStr = is_string($key) ? "'{$key}'" : $key;
            
            if (is_array($value)) {
                $valueStr = $this->arrayToPhpString($value, $depth + 1);
                $elements[] = "{$nextIndent}{$keyStr} => {$valueStr}";
            } elseif (is_string($value)) {
                $elements[] = "{$nextIndent}{$keyStr} => '{$value}'";
            } elseif (is_bool($value)) {
                $elements[] = "{$nextIndent}{$keyStr} => " . ($value ? 'true' : 'false');
            } elseif (is_null($value)) {
                $elements[] = "{$nextIndent}{$keyStr} => null";
            } else {
                $elements[] = "{$nextIndent}{$keyStr} => {$value}";
            }
        }
        
        return "[\n" . implode(",\n", $elements) . ",\n{$indent}]";
    }
    
    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
    
    /**
     * Register a new schema class for documentation generation
     */
    public function registerSchema(string $configKey, string $schemaClass): void
    {
        if (!class_exists($schemaClass)) {
            throw new \InvalidArgumentException("Schema class does not exist: {$schemaClass}");
        }
        
        $this->schemaClasses[$configKey] = $schemaClass;
    }
    
    /**
     * Get registered schema classes
     */
    public function getRegisteredSchemas(): array
    {
        return $this->schemaClasses;
    }
}