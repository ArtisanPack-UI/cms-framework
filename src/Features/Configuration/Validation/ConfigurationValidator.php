<?php

namespace ArtisanPackUI\CMSFramework\Features\Configuration\Validation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use ArtisanPackUI\CMSFramework\Features\Configuration\Schemas\CmsConfigSchema;

/**
 * Configuration Validator
 * 
 * Validates configuration files against their defined schemas to prevent
 * runtime errors from invalid configurations.
 */
class ConfigurationValidator
{
    /**
     * Available schema classes mapped to configuration files
     */
    protected array $schemaClasses = [
        'cms' => CmsConfigSchema::class,
        // Additional schema classes will be added here
    ];
    
    /**
     * Validation results cache
     */
    protected array $validationCache = [];
    
    /**
     * Validate a specific configuration
     */
    public function validate(string $configKey, array $config = null): ValidationResult
    {
        // Use cache if available
        $cacheKey = $configKey . '_' . md5(serialize($config));
        if (isset($this->validationCache[$cacheKey])) {
            return $this->validationCache[$cacheKey];
        }
        
        // Load config if not provided
        if ($config === null) {
            $config = config($configKey, []);
        }
        
        // Get schema class
        $schemaClass = $this->getSchemaClass($configKey);
        if (!$schemaClass) {
            return new ValidationResult(false, [
                "No schema found for configuration key: {$configKey}"
            ]);
        }
        
        // Validate configuration
        $result = $this->validateWithSchema($config, $schemaClass);
        
        // Cache result
        $this->validationCache[$cacheKey] = $result;
        
        return $result;
    }
    
    /**
     * Validate configuration using a schema class
     */
    protected function validateWithSchema(array $config, string $schemaClass): ValidationResult
    {
        try {
            $rules = $schemaClass::getRules();
            $messages = $schemaClass::getMessages();
            
            $validator = Validator::make($config, $rules, $messages);
            
            if ($validator->fails()) {
                return new ValidationResult(false, $validator->errors()->all(), $validator->errors()->toArray());
            }
            
            // Additional custom validations
            $customErrors = $this->runCustomValidations($config, $schemaClass);
            if (!empty($customErrors)) {
                return new ValidationResult(false, $customErrors);
            }
            
            return new ValidationResult(true);
            
        } catch (\Exception $e) {
            return new ValidationResult(false, [
                "Validation error: " . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Run custom validations that can't be handled by Laravel's validator
     */
    protected function runCustomValidations(array $config, string $schemaClass): array
    {
        $errors = [];
        
        // Validate paths exist (for CMS config)
        if ($schemaClass === CmsConfigSchema::class) {
            $errors = array_merge($errors, $this->validateCmsSpecificRules($config));
        }
        
        return $errors;
    }
    
    /**
     * CMS-specific validation rules
     */
    protected function validateCmsSpecificRules(array $config): array
    {
        $errors = [];
        
        // Validate theme directory exists
        if (isset($config['theme']['active'])) {
            $themePath = base_path('themes/' . $config['theme']['active']);
            if (!is_dir($themePath)) {
                $errors[] = "Theme directory does not exist: {$themePath}";
            }
        }
        
        // Validate content type slug uniqueness
        if (isset($config['content_types'])) {
            $slugs = array_column($config['content_types'], 'slug');
            if (count($slugs) !== count(array_unique($slugs))) {
                $errors[] = "Content type slugs must be unique";
            }
        }
        
        // Validate taxonomy content type references
        if (isset($config['taxonomies'])) {
            $contentTypeSlugs = array_keys($config['content_types'] ?? []);
            foreach ($config['taxonomies'] as $taxonomyKey => $taxonomy) {
                if (isset($taxonomy['content_types'])) {
                    foreach ($taxonomy['content_types'] as $contentType) {
                        if (!in_array($contentType, $contentTypeSlugs)) {
                            $errors[] = "Taxonomy '{$taxonomyKey}' references undefined content type: {$contentType}";
                        }
                    }
                }
            }
        }
        
        // Validate rate limiting configuration consistency
        if (isset($config['rate_limiting']['enabled']) && $config['rate_limiting']['enabled']) {
            $requiredSections = ['general', 'auth', 'admin', 'upload'];
            foreach ($requiredSections as $section) {
                if (!isset($config['rate_limiting'][$section])) {
                    $errors[] = "Rate limiting section '{$section}' is required when rate limiting is enabled";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate all registered configurations
     */
    public function validateAll(): array
    {
        $results = [];
        
        foreach (array_keys($this->schemaClasses) as $configKey) {
            $results[$configKey] = $this->validate($configKey);
        }
        
        return $results;
    }
    
    /**
     * Validate environment variables against configuration requirements
     */
    public function validateEnvironment(): ValidationResult
    {
        $errors = [];
        
        // Check required environment variables for CMS config
        $requiredEnvVars = [
            'APP_URL' => 'Application URL is required',
            'APP_NAME' => 'Application name is required',
        ];
        
        foreach ($requiredEnvVars as $var => $message) {
            if (empty(env($var))) {
                $errors[] = $message . " (Environment variable: {$var})";
            }
        }
        
        // Validate environment-specific settings
        $appEnv = env('APP_ENV', 'production');
        
        if ($appEnv === 'production') {
            // Production-specific validations
            if (env('APP_DEBUG', false)) {
                $errors[] = "APP_DEBUG should be false in production environment";
            }
            
            if (empty(env('APP_KEY'))) {
                $errors[] = "APP_KEY is required in production environment";
            }
        }
        
        // Validate media disk configuration
        $mediaDisk = env('MEDIA_DISK', 'public');
        $availableDisks = array_keys(config('filesystems.disks', []));
        if (!in_array($mediaDisk, $availableDisks)) {
            $errors[] = "Media disk '{$mediaDisk}' is not configured in filesystems.disks";
        }
        
        return new ValidationResult(empty($errors), $errors);
    }
    
    /**
     * Get schema class for configuration key
     */
    protected function getSchemaClass(string $configKey): ?string
    {
        return $this->schemaClasses[$configKey] ?? null;
    }
    
    /**
     * Register a new schema class for a configuration key
     */
    public function registerSchema(string $configKey, string $schemaClass): void
    {
        if (!class_exists($schemaClass)) {
            throw new \InvalidArgumentException("Schema class does not exist: {$schemaClass}");
        }
        
        $this->schemaClasses[$configKey] = $schemaClass;
    }
    
    /**
     * Clear validation cache
     */
    public function clearCache(): void
    {
        $this->validationCache = [];
    }
    
    /**
     * Get summary of all validation results
     */
    public function getValidationSummary(): array
    {
        $results = $this->validateAll();
        $envResult = $this->validateEnvironment();
        
        $summary = [
            'total_configurations' => count($results),
            'valid_configurations' => 0,
            'invalid_configurations' => 0,
            'environment_valid' => $envResult->isValid(),
            'total_errors' => 0,
            'configurations' => [],
        ];
        
        foreach ($results as $configKey => $result) {
            if ($result->isValid()) {
                $summary['valid_configurations']++;
            } else {
                $summary['invalid_configurations']++;
                $summary['total_errors'] += count($result->getErrors());
            }
            
            $summary['configurations'][$configKey] = [
                'valid' => $result->isValid(),
                'error_count' => count($result->getErrors()),
                'errors' => $result->getErrors(),
            ];
        }
        
        // Add environment validation to summary
        if (!$envResult->isValid()) {
            $summary['total_errors'] += count($envResult->getErrors());
        }
        
        $summary['environment'] = [
            'valid' => $envResult->isValid(),
            'error_count' => count($envResult->getErrors()),
            'errors' => $envResult->getErrors(),
        ];
        
        return $summary;
    }
}