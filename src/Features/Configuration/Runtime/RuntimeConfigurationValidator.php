<?php

namespace ArtisanPackUI\CMSFramework\Features\Configuration\Runtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ArtisanPackUI\CMSFramework\Features\Configuration\Validation\ConfigurationValidator;
use ArtisanPackUI\CMSFramework\Features\Configuration\Validation\ValidationResult;

/**
 * Runtime Configuration Validator
 * 
 * Provides runtime validation of configurations with caching, monitoring,
 * and real-time validation capabilities during application execution.
 */
class RuntimeConfigurationValidator
{
    /**
     * Configuration validator instance
     */
    protected ConfigurationValidator $validator;
    
    /**
     * Cache key prefix for validation results
     */
    protected string $cachePrefix = 'config_validation_';
    
    /**
     * Cache TTL for validation results (in seconds)
     */
    protected int $cacheTtl = 3600; // 1 hour
    
    /**
     * Whether runtime validation is enabled
     */
    protected bool $enabled = true;
    
    /**
     * Validation results cache
     */
    protected array $validationCache = [];
    
    /**
     * Create a new runtime validator
     */
    public function __construct(ConfigurationValidator $validator)
    {
        $this->validator = $validator;
        $this->enabled = config('cms.runtime_validation.enabled', true);
        $this->cacheTtl = config('cms.runtime_validation.cache_ttl', 3600);
    }
    
    /**
     * Validate configuration at runtime with caching
     */
    public function validateRuntime(string $configKey): ValidationResult
    {
        if (!$this->enabled) {
            return ValidationResult::success(['runtime_validation_disabled' => true]);
        }
        
        $cacheKey = $this->getCacheKey($configKey);
        
        // Check memory cache first
        if (isset($this->validationCache[$cacheKey])) {
            return $this->validationCache[$cacheKey];
        }
        
        // Check persistent cache
        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult !== null) {
            $result = $this->deserializeValidationResult($cachedResult);
            $this->validationCache[$cacheKey] = $result;
            return $result;
        }
        
        // Perform validation
        $result = $this->validator->validate($configKey);
        
        // Add runtime metadata
        $result->setMetadata('validated_at', now()->toISOString());
        $result->setMetadata('validation_source', 'runtime');
        $result->setMetadata('config_checksum', $this->getConfigChecksum($configKey));
        
        // Cache the result
        $this->cacheValidationResult($cacheKey, $result);
        $this->validationCache[$cacheKey] = $result;
        
        // Log validation issues
        if ($result->failed()) {
            $this->logValidationFailure($configKey, $result);
        }
        
        return $result;
    }
    
    /**
     * Validate configuration on bootstrap
     */
    public function validateOnBootstrap(): array
    {
        $results = [];
        $criticalConfigs = $this->getCriticalConfigurations();
        
        foreach ($criticalConfigs as $configKey) {
            $result = $this->validateRuntime($configKey);
            $results[$configKey] = $result;
            
            // Stop application if critical configuration is invalid
            if ($result->failed() && $this->isCriticalConfiguration($configKey)) {
                $this->handleCriticalConfigurationFailure($configKey, $result);
            }
        }
        
        return $results;
    }
    
    /**
     * Monitor configuration changes and invalidate cache
     */
    public function monitorConfigurationChanges(string $configKey): void
    {
        $currentChecksum = $this->getConfigChecksum($configKey);
        $cacheKey = $this->getCacheKey($configKey);
        
        // Get cached result to compare checksums
        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult !== null) {
            $cachedChecksum = $cachedResult['metadata']['config_checksum'] ?? null;
            
            if ($cachedChecksum && $cachedChecksum !== $currentChecksum) {
                // Configuration changed, invalidate cache
                $this->invalidateCache($configKey);
                
                // Log configuration change
                Log::info("Configuration change detected for {$configKey}", [
                    'old_checksum' => $cachedChecksum,
                    'new_checksum' => $currentChecksum,
                ]);
                
                // Re-validate configuration
                $this->validateRuntime($configKey);
            }
        }
    }
    
    /**
     * Validate configuration with performance monitoring
     */
    public function validateWithMonitoring(string $configKey): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        $result = $this->validateRuntime($configKey);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $performanceMetrics = [
            'execution_time' => round(($endTime - $startTime) * 1000, 2), // milliseconds
            'memory_usage' => $endMemory - $startMemory, // bytes
            'peak_memory' => memory_get_peak_usage(),
            'cache_hit' => isset($this->validationCache[$this->getCacheKey($configKey)]),
        ];
        
        return [
            'result' => $result,
            'performance' => $performanceMetrics,
        ];
    }
    
    /**
     * Batch validate multiple configurations
     */
    public function batchValidate(array $configKeys): array
    {
        $results = [];
        $startTime = microtime(true);
        
        foreach ($configKeys as $configKey) {
            $results[$configKey] = $this->validateRuntime($configKey);
        }
        
        $totalTime = microtime(true) - $startTime;
        
        return [
            'results' => $results,
            'batch_metrics' => [
                'total_configs' => count($configKeys),
                'total_time' => round($totalTime * 1000, 2), // milliseconds
                'average_time' => round(($totalTime / count($configKeys)) * 1000, 2),
                'failed_count' => count(array_filter($results, fn($r) => $r->failed())),
            ],
        ];
    }
    
    /**
     * Get critical configurations that must be validated on bootstrap
     */
    protected function getCriticalConfigurations(): array
    {
        return config('cms.runtime_validation.critical_configs', [
            'cms',
            'app',
            'database',
        ]);
    }
    
    /**
     * Check if a configuration is critical
     */
    protected function isCriticalConfiguration(string $configKey): bool
    {
        $criticalConfigs = $this->getCriticalConfigurations();
        return in_array($configKey, $criticalConfigs);
    }
    
    /**
     * Handle critical configuration failure
     */
    protected function handleCriticalConfigurationFailure(string $configKey, ValidationResult $result): void
    {
        $errorMessage = "Critical configuration '{$configKey}' validation failed: " . $result->getFirstError();
        
        Log::critical($errorMessage, [
            'config_key' => $configKey,
            'errors' => $result->getErrors(),
            'detailed_errors' => $result->getDetailedErrors(),
        ]);
        
        // In production, you might want to gracefully degrade or show a maintenance page
        if (app()->environment('production')) {
            // Log the error but don't stop the application
            return;
        }
        
        // In development, throw an exception to help with debugging
        throw new \RuntimeException($errorMessage);
    }
    
    /**
     * Get cache key for configuration validation
     */
    protected function getCacheKey(string $configKey): string
    {
        return $this->cachePrefix . $configKey;
    }
    
    /**
     * Get configuration checksum for change detection
     */
    protected function getConfigChecksum(string $configKey): string
    {
        $config = config($configKey, []);
        return md5(serialize($config));
    }
    
    /**
     * Cache validation result
     */
    protected function cacheValidationResult(string $cacheKey, ValidationResult $result): void
    {
        $serialized = $this->serializeValidationResult($result);
        Cache::put($cacheKey, $serialized, $this->cacheTtl);
    }
    
    /**
     * Serialize validation result for caching
     */
    protected function serializeValidationResult(ValidationResult $result): array
    {
        return [
            'valid' => $result->isValid(),
            'errors' => $result->getErrors(),
            'detailed_errors' => $result->getDetailedErrors(),
            'metadata' => $result->getMetadata(),
            'cached_at' => now()->toISOString(),
        ];
    }
    
    /**
     * Deserialize validation result from cache
     */
    protected function deserializeValidationResult(array $cached): ValidationResult
    {
        $result = new ValidationResult(
            $cached['valid'],
            $cached['errors'],
            $cached['detailed_errors'],
            $cached['metadata']
        );
        
        $result->setMetadata('cache_hit', true);
        $result->setMetadata('cached_at', $cached['cached_at']);
        
        return $result;
    }
    
    /**
     * Invalidate cache for a configuration
     */
    public function invalidateCache(string $configKey): void
    {
        $cacheKey = $this->getCacheKey($configKey);
        Cache::forget($cacheKey);
        unset($this->validationCache[$cacheKey]);
    }
    
    /**
     * Invalidate all configuration validation cache
     */
    public function invalidateAllCache(): void
    {
        $keys = Cache::get($this->cachePrefix . 'keys', []);
        
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        
        Cache::forget($this->cachePrefix . 'keys');
        $this->validationCache = [];
    }
    
    /**
     * Log validation failure
     */
    protected function logValidationFailure(string $configKey, ValidationResult $result): void
    {
        Log::warning("Configuration validation failed for '{$configKey}'", [
            'config_key' => $configKey,
            'error_count' => $result->getErrorCount(),
            'errors' => $result->getErrors(),
            'detailed_errors' => $result->getDetailedErrors(),
        ]);
    }
    
    /**
     * Get validation statistics
     */
    public function getValidationStats(): array
    {
        $cacheKeys = array_keys($this->validationCache);
        $stats = [
            'enabled' => $this->enabled,
            'cache_ttl' => $this->cacheTtl,
            'memory_cache_size' => count($this->validationCache),
            'memory_cache_keys' => $cacheKeys,
            'validation_results' => [],
        ];
        
        foreach ($this->validationCache as $key => $result) {
            $configKey = str_replace($this->cachePrefix, '', $key);
            $stats['validation_results'][$configKey] = [
                'valid' => $result->isValid(),
                'error_count' => $result->getErrorCount(),
                'validated_at' => $result->getMetadataValue('validated_at'),
                'cache_hit' => $result->getMetadataValue('cache_hit', false),
            ];
        }
        
        return $stats;
    }
    
    /**
     * Enable runtime validation
     */
    public function enable(): void
    {
        $this->enabled = true;
    }
    
    /**
     * Disable runtime validation
     */
    public function disable(): void
    {
        $this->enabled = false;
    }
    
    /**
     * Check if runtime validation is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}