<?php

namespace ArtisanPackUI\CMSFramework\Features\Configuration\Migrations;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Configuration Migrator
 * 
 * Handles configuration migrations, version tracking, and rollback functionality
 * for configuration file changes across different versions of the CMS.
 */
class ConfigurationMigrator
{
    /**
     * Configuration migrations directory
     */
    protected string $migrationsPath;
    
    /**
     * Configuration backups directory
     */
    protected string $backupsPath;
    
    /**
     * Migration version tracking file
     */
    protected string $versionFile;
    
    /**
     * Current configuration version
     */
    protected ?string $currentVersion = null;
    
    /**
     * Create a new configuration migrator
     */
    public function __construct()
    {
        $this->migrationsPath = base_path('config/migrations');
        $this->backupsPath = base_path('config/backups');
        $this->versionFile = base_path('config/version.json');
        
        $this->ensureDirectoriesExist();
        $this->loadCurrentVersion();
    }
    
    /**
     * Run pending configuration migrations
     */
    public function migrate(): array
    {
        $results = [];
        $pendingMigrations = $this->getPendingMigrations();
        
        if (empty($pendingMigrations)) {
            return ['message' => 'No pending configuration migrations.'];
        }
        
        foreach ($pendingMigrations as $migration) {
            try {
                $this->createBackup($migration['version']);
                
                $migrationInstance = $this->loadMigration($migration['file']);
                $migrationResult = $migrationInstance->up();
                
                $this->updateVersion($migration['version']);
                
                $results[] = [
                    'version' => $migration['version'],
                    'status' => 'success',
                    'result' => $migrationResult,
                ];
                
            } catch (\Exception $e) {
                $results[] = [
                    'version' => $migration['version'],
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                
                // Stop on first failure to prevent cascade issues
                break;
            }
        }
        
        return $results;
    }
    
    /**
     * Rollback configuration to a specific version
     */
    public function rollback(?string $targetVersion = null): array
    {
        if (!$targetVersion) {
            $targetVersion = $this->getPreviousVersion();
        }
        
        if (!$targetVersion) {
            throw new \Exception('No target version specified and no previous version available.');
        }
        
        $rollbackMigrations = $this->getRollbackMigrations($targetVersion);
        $results = [];
        
        foreach ($rollbackMigrations as $migration) {
            try {
                $this->createBackup($migration['version'] . '_rollback');
                
                $migrationInstance = $this->loadMigration($migration['file']);
                
                if (method_exists($migrationInstance, 'down')) {
                    $migrationResult = $migrationInstance->down();
                } else {
                    $migrationResult = $this->restoreFromBackup($migration['version']);
                }
                
                $results[] = [
                    'version' => $migration['version'],
                    'status' => 'rolled_back',
                    'result' => $migrationResult,
                ];
                
            } catch (\Exception $e) {
                $results[] = [
                    'version' => $migration['version'],
                    'status' => 'rollback_failed',
                    'error' => $e->getMessage(),
                ];
                
                break;
            }
        }
        
        $this->updateVersion($targetVersion);
        
        return $results;
    }
    
    /**
     * Create a new configuration migration file
     */
    public function createMigration(string $name, string $description = ''): string
    {
        $version = $this->generateVersion();
        $className = 'ConfigMigration' . $version . Str::studly($name);
        $filename = "{$version}_{$name}.php";
        $filepath = $this->migrationsPath . '/' . $filename;
        
        $stub = $this->getMigrationStub();
        $content = str_replace(
            ['{{CLASS_NAME}}', '{{DESCRIPTION}}', '{{VERSION}}'],
            [$className, $description, $version],
            $stub
        );
        
        File::put($filepath, $content);
        
        return $filepath;
    }
    
    /**
     * Get pending configuration migrations
     */
    public function getPendingMigrations(): array
    {
        $allMigrations = $this->getAllMigrations();
        $currentVersion = $this->getCurrentVersion();
        
        return array_filter($allMigrations, function ($migration) use ($currentVersion) {
            return version_compare($migration['version'], $currentVersion, '>');
        });
    }
    
    /**
     * Get all available migrations
     */
    public function getAllMigrations(): array
    {
        $migrations = [];
        $files = File::glob($this->migrationsPath . '/*.php');
        
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/^(\d{14})_(.+)\.php$/', $filename, $matches)) {
                $migrations[] = [
                    'version' => $matches[1],
                    'name' => $matches[2],
                    'file' => $file,
                    'filename' => $filename,
                ];
            }
        }
        
        // Sort by version
        usort($migrations, function ($a, $b) {
            return version_compare($a['version'], $b['version']);
        });
        
        return $migrations;
    }
    
    /**
     * Get rollback migrations for a target version
     */
    protected function getRollbackMigrations(string $targetVersion): array
    {
        $allMigrations = $this->getAllMigrations();
        $currentVersion = $this->getCurrentVersion();
        
        $rollbackMigrations = array_filter($allMigrations, function ($migration) use ($targetVersion, $currentVersion) {
            return version_compare($migration['version'], $targetVersion, '>') &&
                   version_compare($migration['version'], $currentVersion, '<=');
        });
        
        // Sort in reverse order for rollback
        return array_reverse($rollbackMigrations);
    }
    
    /**
     * Load and instantiate a migration file
     */
    protected function loadMigration(string $file): ConfigurationMigration
    {
        require_once $file;
        
        $filename = basename($file, '.php');
        $className = 'ConfigMigration' . substr($filename, 0, 14) . Str::studly(substr($filename, 15));
        
        if (!class_exists($className)) {
            throw new \Exception("Migration class {$className} not found in {$file}");
        }
        
        return new $className();
    }
    
    /**
     * Create backup of current configuration state
     */
    protected function createBackup(string $version): void
    {
        $backupPath = $this->backupsPath . "/backup_{$version}_" . date('Y-m-d_H-i-s');
        File::makeDirectory($backupPath, 0755, true);
        
        // Backup all config files
        $configFiles = File::glob(config_path('*.php'));
        foreach ($configFiles as $file) {
            $filename = basename($file);
            File::copy($file, "{$backupPath}/{$filename}");
        }
        
        // Backup version info
        File::put("{$backupPath}/version.json", json_encode([
            'version' => $this->getCurrentVersion(),
            'timestamp' => now()->toISOString(),
            'backup_reason' => "Migration to version {$version}",
        ], JSON_PRETTY_PRINT));
    }
    
    /**
     * Restore configuration from backup
     */
    protected function restoreFromBackup(string $version): array
    {
        $backupDirs = File::directories($this->backupsPath);
        $targetBackup = null;
        
        // Find backup for this version
        foreach ($backupDirs as $dir) {
            if (str_contains(basename($dir), "backup_{$version}_")) {
                $targetBackup = $dir;
                break;
            }
        }
        
        if (!$targetBackup) {
            throw new \Exception("No backup found for version {$version}");
        }
        
        $restoredFiles = [];
        $backupFiles = File::glob("{$targetBackup}/*.php");
        
        foreach ($backupFiles as $file) {
            $filename = basename($file);
            $targetFile = config_path($filename);
            
            File::copy($file, $targetFile);
            $restoredFiles[] = $filename;
        }
        
        return ['restored_files' => $restoredFiles];
    }
    
    /**
     * Update current version
     */
    protected function updateVersion(string $version): void
    {
        $versionData = [
            'current_version' => $version,
            'updated_at' => now()->toISOString(),
            'previous_version' => $this->currentVersion,
        ];
        
        File::put($this->versionFile, json_encode($versionData, JSON_PRETTY_PRINT));
        $this->currentVersion = $version;
    }
    
    /**
     * Get current configuration version
     */
    public function getCurrentVersion(): string
    {
        return $this->currentVersion ?? '00000000000000';
    }
    
    /**
     * Get previous version
     */
    protected function getPreviousVersion(): ?string
    {
        if (!File::exists($this->versionFile)) {
            return null;
        }
        
        $versionData = json_decode(File::get($this->versionFile), true);
        return $versionData['previous_version'] ?? null;
    }
    
    /**
     * Load current version from file
     */
    protected function loadCurrentVersion(): void
    {
        if (File::exists($this->versionFile)) {
            $versionData = json_decode(File::get($this->versionFile), true);
            $this->currentVersion = $versionData['current_version'] ?? null;
        }
    }
    
    /**
     * Generate new migration version
     */
    protected function generateVersion(): string
    {
        return date('YmdHis');
    }
    
    /**
     * Ensure required directories exist
     */
    protected function ensureDirectoriesExist(): void
    {
        File::ensureDirectoryExists($this->migrationsPath);
        File::ensureDirectoryExists($this->backupsPath);
    }
    
    /**
     * Get migration stub content
     */
    protected function getMigrationStub(): string
    {
        return '<?php

/**
 * Configuration Migration: {{DESCRIPTION}}
 * Version: {{VERSION}}
 * Generated: ' . now()->toDateTimeString() . '
 */
class {{CLASS_NAME}} extends ArtisanPackUI\CMSFramework\Features\Configuration\Migrations\ConfigurationMigration
{
    /**
     * Migration description
     */
    protected string $description = "{{DESCRIPTION}}";
    
    /**
     * Migration version
     */
    protected string $version = "{{VERSION}}";
    
    /**
     * Run the migration
     */
    public function up(): array
    {
        $changes = [];
        
        // TODO: Implement your configuration changes here
        // Example:
        // $changes[] = $this->updateConfigValue("cms.site.name", "New Site Name");
        // $changes[] = $this->addConfigSection("new_feature", ["enabled" => true]);
        // $changes[] = $this->removeConfigKey("deprecated.setting");
        
        return $changes;
    }
    
    /**
     * Rollback the migration
     */
    public function down(): array
    {
        $changes = [];
        
        // TODO: Implement rollback logic here
        // This should reverse the changes made in up()
        
        return $changes;
    }
}';
    }
    
    /**
     * Get migration status
     */
    public function getStatus(): array
    {
        $allMigrations = $this->getAllMigrations();
        $pendingMigrations = $this->getPendingMigrations();
        
        return [
            'current_version' => $this->getCurrentVersion(),
            'total_migrations' => count($allMigrations),
            'pending_migrations' => count($pendingMigrations),
            'last_migration' => !empty($allMigrations) ? end($allMigrations) : null,
            'next_migration' => !empty($pendingMigrations) ? $pendingMigrations[0] : null,
            'backups_available' => count(File::directories($this->backupsPath)),
        ];
    }
}