<?php

namespace ArtisanPackUI\CMSFramework\Features\Configuration\Migrations;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Arr;

/**
 * Configuration Migration Base Class
 * 
 * Provides common functionality and helper methods for configuration migrations.
 * All configuration migrations should extend this class.
 */
abstract class ConfigurationMigration
{
    /**
     * Migration description
     */
    protected string $description = '';
    
    /**
     * Migration version
     */
    protected string $version = '';
    
    /**
     * Run the migration
     */
    abstract public function up(): array;
    
    /**
     * Rollback the migration (optional)
     */
    public function down(): array
    {
        return ['message' => 'Rollback not implemented for this migration.'];
    }
    
    /**
     * Update a configuration value
     */
    protected function updateConfigValue(string $key, mixed $value, string $configFile = null): array
    {
        $parts = explode('.', $key);
        $configFile = $configFile ?? $parts[0];
        $configPath = config_path("{$configFile}.php");
        
        if (!File::exists($configPath)) {
            throw new \Exception("Configuration file not found: {$configPath}");
        }
        
        // Load current config
        $config = require $configPath;
        
        // Update the value using dot notation
        Arr::set($config, $key, $value);
        
        // Write back to file
        $this->writeConfigFile($configPath, $config);
        
        return [
            'action' => 'update_config_value',
            'key' => $key,
            'value' => $value,
            'file' => $configFile,
        ];
    }
    
    /**
     * Add a new configuration section
     */
    protected function addConfigSection(string $section, array $data, string $configFile = 'cms'): array
    {
        $configPath = config_path("{$configFile}.php");
        
        if (!File::exists($configPath)) {
            throw new \Exception("Configuration file not found: {$configPath}");
        }
        
        // Load current config
        $config = require $configPath;
        
        // Add new section
        $config[$section] = $data;
        
        // Write back to file
        $this->writeConfigFile($configPath, $config);
        
        return [
            'action' => 'add_config_section',
            'section' => $section,
            'data' => $data,
            'file' => $configFile,
        ];
    }
    
    /**
     * Remove a configuration key
     */
    protected function removeConfigKey(string $key, string $configFile = null): array
    {
        $parts = explode('.', $key);
        $configFile = $configFile ?? $parts[0];
        $configPath = config_path("{$configFile}.php");
        
        if (!File::exists($configPath)) {
            throw new \Exception("Configuration file not found: {$configPath}");
        }
        
        // Load current config
        $config = require $configPath;
        
        // Remove the key using dot notation
        Arr::forget($config, $key);
        
        // Write back to file
        $this->writeConfigFile($configPath, $config);
        
        return [
            'action' => 'remove_config_key',
            'key' => $key,
            'file' => $configFile,
        ];
    }
    
    /**
     * Rename a configuration key
     */
    protected function renameConfigKey(string $oldKey, string $newKey, string $configFile = null): array
    {
        $parts = explode('.', $oldKey);
        $configFile = $configFile ?? $parts[0];
        $configPath = config_path("{$configFile}.php");
        
        if (!File::exists($configPath)) {
            throw new \Exception("Configuration file not found: {$configPath}");
        }
        
        // Load current config
        $config = require $configPath;
        
        // Get the old value
        $oldValue = Arr::get($config, $oldKey);
        
        if ($oldValue === null) {
            throw new \Exception("Configuration key not found: {$oldKey}");
        }
        
        // Set new key and remove old key
        Arr::set($config, $newKey, $oldValue);
        Arr::forget($config, $oldKey);
        
        // Write back to file
        $this->writeConfigFile($configPath, $config);
        
        return [
            'action' => 'rename_config_key',
            'old_key' => $oldKey,
            'new_key' => $newKey,
            'value' => $oldValue,
            'file' => $configFile,
        ];
    }
    
    /**
     * Merge configuration arrays
     */
    protected function mergeConfigArray(string $key, array $newData, string $configFile = null): array
    {
        $parts = explode('.', $key);
        $configFile = $configFile ?? $parts[0];
        $configPath = config_path("{$configFile}.php");
        
        if (!File::exists($configPath)) {
            throw new \Exception("Configuration file not found: {$configPath}");
        }
        
        // Load current config
        $config = require $configPath;
        
        // Get current array value
        $currentValue = Arr::get($config, $key, []);
        
        if (!is_array($currentValue)) {
            throw new \Exception("Configuration key is not an array: {$key}");
        }
        
        // Merge arrays
        $mergedValue = array_merge($currentValue, $newData);
        Arr::set($config, $key, $mergedValue);
        
        // Write back to file
        $this->writeConfigFile($configPath, $config);
        
        return [
            'action' => 'merge_config_array',
            'key' => $key,
            'new_data' => $newData,
            'merged_count' => count($mergedValue),
            'file' => $configFile,
        ];
    }
    
    /**
     * Create a new configuration file
     */
    protected function createConfigFile(string $filename, array $config): array
    {
        $configPath = config_path("{$filename}.php");
        
        if (File::exists($configPath)) {
            throw new \Exception("Configuration file already exists: {$configPath}");
        }
        
        $this->writeConfigFile($configPath, $config);
        
        return [
            'action' => 'create_config_file',
            'file' => $filename,
            'keys_count' => count($config),
        ];
    }
    
    /**
     * Backup a configuration file
     */
    protected function backupConfigFile(string $configFile): array
    {
        $configPath = config_path("{$configFile}.php");
        $backupPath = config_path("{$configFile}.backup." . date('Y-m-d-H-i-s') . '.php');
        
        if (!File::exists($configPath)) {
            throw new \Exception("Configuration file not found: {$configPath}");
        }
        
        File::copy($configPath, $backupPath);
        
        return [
            'action' => 'backup_config_file',
            'original' => $configPath,
            'backup' => $backupPath,
        ];
    }
    
    /**
     * Validate configuration after changes
     */
    protected function validateConfiguration(string $configFile = 'cms'): array
    {
        // This would integrate with the ConfigurationValidator
        // For now, just check if the file is valid PHP and can be loaded
        $configPath = config_path("{$configFile}.php");
        
        try {
            $config = require $configPath;
            
            if (!is_array($config)) {
                throw new \Exception("Configuration file must return an array");
            }
            
            return [
                'action' => 'validate_configuration',
                'file' => $configFile,
                'status' => 'valid',
                'keys_count' => count($config),
            ];
            
        } catch (\Exception $e) {
            return [
                'action' => 'validate_configuration',
                'file' => $configFile,
                'status' => 'invalid',
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Write configuration array to file
     */
    protected function writeConfigFile(string $path, array $config): void
    {
        $content = "<?php\n\nreturn " . $this->arrayToPhp($config, 0) . ";\n";
        File::put($path, $content);
    }
    
    /**
     * Convert array to PHP code string with proper formatting
     */
    protected function arrayToPhp(array $array, int $depth = 0): string
    {
        $indent = str_repeat('    ', $depth);
        $nextIndent = str_repeat('    ', $depth + 1);
        
        $elements = [];
        
        foreach ($array as $key => $value) {
            $keyStr = is_string($key) ? "'{$key}'" : $key;
            
            if (is_array($value)) {
                $valueStr = $this->arrayToPhp($value, $depth + 1);
                $elements[] = "{$nextIndent}{$keyStr} => {$valueStr}";
            } elseif (is_string($value)) {
                $escapedValue = addslashes($value);
                $elements[] = "{$nextIndent}{$keyStr} => '{$escapedValue}'";
            } elseif (is_bool($value)) {
                $boolStr = $value ? 'true' : 'false';
                $elements[] = "{$nextIndent}{$keyStr} => {$boolStr}";
            } elseif (is_null($value)) {
                $elements[] = "{$nextIndent}{$keyStr} => null";
            } elseif (is_numeric($value)) {
                $elements[] = "{$nextIndent}{$keyStr} => {$value}";
            } else {
                // Handle other types by converting to string
                $stringValue = addslashes((string) $value);
                $elements[] = "{$nextIndent}{$keyStr} => '{$stringValue}'";
            }
        }
        
        if (empty($elements)) {
            return '[]';
        }
        
        return "[\n" . implode(",\n", $elements) . ",\n{$indent}]";
    }
    
    /**
     * Get migration description
     */
    public function getDescription(): string
    {
        return $this->description;
    }
    
    /**
     * Get migration version
     */
    public function getVersion(): string
    {
        return $this->version;
    }
    
    /**
     * Check if a configuration key exists
     */
    protected function configKeyExists(string $key, string $configFile = null): bool
    {
        $parts = explode('.', $key);
        $configFile = $configFile ?? $parts[0];
        $configPath = config_path("{$configFile}.php");
        
        if (!File::exists($configPath)) {
            return false;
        }
        
        $config = require $configPath;
        return Arr::has($config, $key);
    }
    
    /**
     * Get current configuration value
     */
    protected function getCurrentConfigValue(string $key, mixed $default = null, string $configFile = null): mixed
    {
        $parts = explode('.', $key);
        $configFile = $configFile ?? $parts[0];
        $configPath = config_path("{$configFile}.php");
        
        if (!File::exists($configPath)) {
            return $default;
        }
        
        $config = require $configPath;
        return Arr::get($config, $key, $default);
    }
}