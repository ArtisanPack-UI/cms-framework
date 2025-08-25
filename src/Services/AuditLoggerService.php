<?php

declare(strict_types=1);

/**
 * Audit Logger Service
 *
 * Provides comprehensive audit logging for sensitive operations in the CMS framework.
 * Tracks user actions, permission changes, authentication events, data modifications,
 * and administrative operations with detailed context for compliance and security monitoring.
 *
 * @since 1.0.0
 * @author Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Services;

use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Audit Logger Service
 *
 * Centralized service for logging sensitive operations and user actions.
 */
class AuditLoggerService
{
    /**
     * Structured logger service for audit logs.
     */
    private StructuredLoggerService $logger;

    /**
     * Request instance for context.
     */
    private ?Request $request;

    /**
     * Audit event categories.
     */
    private array $categories = [
        'authentication' => 'User authentication events',
        'authorization' => 'Permission and access control events',
        'user_management' => 'User account management',
        'content_management' => 'Content creation, updates, and deletion',
        'media_management' => 'Media upload, modification, and deletion',
        'plugin_management' => 'Plugin installation, activation, and configuration',
        'system_configuration' => 'System settings and configuration changes',
        'security' => 'Security-related events and violations',
        'data_access' => 'Sensitive data access and export',
        'admin_actions' => 'Administrative operations',
    ];

    /**
     * Create a new audit logger instance.
     */
    public function __construct(StructuredLoggerService $logger, ?Request $request = null)
    {
        $this->logger = $logger;
        $this->request = $request ?? request();
    }

    /**
     * Log user authentication event.
     */
    public function logAuthentication(string $action, ?User $user = null, bool $success = true, array $context = []): void
    {
        $user = $user ?? Auth::user();
        
        $auditContext = array_merge($context, [
            'action' => $action,
            'success' => $success,
            'user_id' => $user?->id,
            'email' => $user?->email,
            'username' => $user?->username,
            'ip_address' => $this->request?->ip(),
            'user_agent' => $this->request?->userAgent(),
            'session_id' => session()->getId(),
            'timestamp' => now()->toISOString(),
        ]);

        $this->logger->audit("Authentication: {$action}", $auditContext);
        
        // Store in audit trail table if enabled
        if (Config::get('cms.audit.store_in_database', true)) {
            $this->storeAuditRecord('authentication', $action, $auditContext, $user);
        }
    }

    /**
     * Log authorization event.
     */
    public function logAuthorization(string $action, string $resource, bool $granted = false, ?User $user = null, array $context = []): void
    {
        $user = $user ?? Auth::user();
        
        $auditContext = array_merge($context, [
            'action' => $action,
            'resource' => $resource,
            'granted' => $granted,
            'user_id' => $user?->id,
            'user_role' => $user?->role?->name,
            'ip_address' => $this->request?->ip(),
            'timestamp' => now()->toISOString(),
        ]);

        $level = $granted ? 'info' : 'warning';
        $this->logger->authorization("Authorization: {$action} on {$resource}", $user?->id ?? 0, $resource, $action, $granted, $auditContext);
        
        if (Config::get('cms.audit.store_in_database', true)) {
            $this->storeAuditRecord('authorization', "{$action} on {$resource}", $auditContext, $user);
        }
    }

    /**
     * Log user management action.
     */
    public function logUserManagement(string $action, User $targetUser, ?User $actingUser = null, array $context = []): void
    {
        $actingUser = $actingUser ?? Auth::user();
        
        $auditContext = array_merge($context, [
            'action' => $action,
            'target_user_id' => $targetUser->id,
            'target_email' => $targetUser->email,
            'target_username' => $targetUser->username,
            'acting_user_id' => $actingUser?->id,
            'acting_user_email' => $actingUser?->email,
            'ip_address' => $this->request?->ip(),
            'timestamp' => now()->toISOString(),
        ]);

        $this->logger->audit("User Management: {$action}", $auditContext);
        
        if (Config::get('cms.audit.store_in_database', true)) {
            $this->storeAuditRecord('user_management', $action, $auditContext, $actingUser);
        }
    }

    /**
     * Log content management action.
     */
    public function logContentManagement(string $action, string $contentType, int $contentId, ?User $user = null, array $context = []): void
    {
        $user = $user ?? Auth::user();
        
        $auditContext = array_merge($context, [
            'action' => $action,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'user_id' => $user?->id,
            'ip_address' => $this->request?->ip(),
            'timestamp' => now()->toISOString(),
        ]);

        $this->logger->audit("Content Management: {$action} {$contentType} #{$contentId}", $auditContext);
        
        if (Config::get('cms.audit.store_in_database', true)) {
            $this->storeAuditRecord('content_management', "{$action} {$contentType}", $auditContext, $user);
        }
    }

    /**
     * Log media management action.
     */
    public function logMediaManagement(string $action, int $mediaId, string $filename, ?User $user = null, array $context = []): void
    {
        $user = $user ?? Auth::user();
        
        $auditContext = array_merge($context, [
            'action' => $action,
            'media_id' => $mediaId,
            'filename' => $filename,
            'user_id' => $user?->id,
            'ip_address' => $this->request?->ip(),
            'timestamp' => now()->toISOString(),
        ]);

        $this->logger->audit("Media Management: {$action} {$filename}", $auditContext);
        
        if (Config::get('cms.audit.store_in_database', true)) {
            $this->storeAuditRecord('media_management', "{$action} media", $auditContext, $user);
        }
    }

    /**
     * Log plugin management action.
     */
    public function logPluginManagement(string $action, string $pluginSlug, ?User $user = null, array $context = []): void
    {
        $user = $user ?? Auth::user();
        
        $auditContext = array_merge($context, [
            'action' => $action,
            'plugin_slug' => $pluginSlug,
            'user_id' => $user?->id,
            'ip_address' => $this->request?->ip(),
            'timestamp' => now()->toISOString(),
        ]);

        $this->logger->audit("Plugin Management: {$action} {$pluginSlug}", $auditContext);
        
        if (Config::get('cms.audit.store_in_database', true)) {
            $this->storeAuditRecord('plugin_management', "{$action} plugin", $auditContext, $user);
        }
    }

    /**
     * Log system configuration change.
     */
    public function logSystemConfiguration(string $action, string $configKey, $oldValue = null, $newValue = null, ?User $user = null, array $context = []): void
    {
        $user = $user ?? Auth::user();
        
        $auditContext = array_merge($context, [
            'action' => $action,
            'config_key' => $configKey,
            'old_value' => $this->sanitizeValue($oldValue),
            'new_value' => $this->sanitizeValue($newValue),
            'user_id' => $user?->id,
            'ip_address' => $this->request?->ip(),
            'timestamp' => now()->toISOString(),
        ]);

        $this->logger->audit("System Configuration: {$action} {$configKey}", $auditContext);
        
        if (Config::get('cms.audit.store_in_database', true)) {
            $this->storeAuditRecord('system_configuration', "{$action} configuration", $auditContext, $user);
        }
    }

    /**
     * Log security event.
     */
    public function logSecurity(string $event, string $severity = 'warning', ?User $user = null, array $context = []): void
    {
        $user = $user ?? Auth::user();
        
        $auditContext = array_merge($context, [
            'event' => $event,
            'severity' => $severity,
            'user_id' => $user?->id,
            'ip_address' => $this->request?->ip(),
            'user_agent' => $this->request?->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);

        $this->logger->security("Security Event: {$event}", $auditContext, $severity);
        
        if (Config::get('cms.audit.store_in_database', true)) {
            $this->storeAuditRecord('security', $event, $auditContext, $user);
        }
    }

    /**
     * Log sensitive data access.
     */
    public function logDataAccess(string $action, string $dataType, array $records = [], ?User $user = null, array $context = []): void
    {
        $user = $user ?? Auth::user();
        
        $auditContext = array_merge($context, [
            'action' => $action,
            'data_type' => $dataType,
            'record_count' => count($records),
            'record_ids' => array_slice($records, 0, 100), // Limit to first 100 IDs
            'user_id' => $user?->id,
            'ip_address' => $this->request?->ip(),
            'timestamp' => now()->toISOString(),
        ]);

        $this->logger->audit("Data Access: {$action} {$dataType}", $auditContext);
        
        if (Config::get('cms.audit.store_in_database', true)) {
            $this->storeAuditRecord('data_access', "{$action} {$dataType}", $auditContext, $user);
        }
    }

    /**
     * Log administrative action.
     */
    public function logAdminAction(string $action, array $context = [], ?User $user = null): void
    {
        $user = $user ?? Auth::user();
        
        $auditContext = array_merge($context, [
            'action' => $action,
            'user_id' => $user?->id,
            'ip_address' => $this->request?->ip(),
            'timestamp' => now()->toISOString(),
        ]);

        $this->logger->audit("Admin Action: {$action}", $auditContext);
        
        if (Config::get('cms.audit.store_in_database', true)) {
            $this->storeAuditRecord('admin_actions', $action, $auditContext, $user);
        }
    }

    /**
     * Log model changes (create, update, delete).
     */
    public function logModelChange(string $action, Model $model, array $changes = [], ?User $user = null, array $context = []): void
    {
        $user = $user ?? Auth::user();
        $modelClass = get_class($model);
        $modelName = class_basename($modelClass);
        
        $auditContext = array_merge($context, [
            'action' => $action,
            'model_class' => $modelClass,
            'model_id' => $model->getKey(),
            'changes' => $this->sanitizeChanges($changes),
            'user_id' => $user?->id,
            'ip_address' => $this->request?->ip(),
            'timestamp' => now()->toISOString(),
        ]);

        $this->logger->audit("Model Change: {$action} {$modelName} #{$model->getKey()}", $auditContext);
        
        if (Config::get('cms.audit.store_in_database', true)) {
            $category = $this->getModelCategory($modelClass);
            $this->storeAuditRecord($category, "{$action} {$modelName}", $auditContext, $user);
        }
    }

    /**
     * Store audit record in database.
     */
    private function storeAuditRecord(string $category, string $action, array $context, ?User $user): void
    {
        try {
            DB::table('audit_logs')->insert([
                'category' => $category,
                'action' => $action,
                'user_id' => $user?->id,
                'ip_address' => $this->request?->ip(),
                'user_agent' => $this->request?->userAgent(),
                'context' => json_encode($context),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log the error but don't fail the main operation
            $this->logger->error('Failed to store audit record', [
                'error' => $e->getMessage(),
                'category' => $category,
                'action' => $action,
            ]);
        }
    }

    /**
     * Get model category for audit logging.
     */
    private function getModelCategory(string $modelClass): string
    {
        $modelName = class_basename($modelClass);
        
        return match ($modelName) {
            'User' => 'user_management',
            'Content', 'Post', 'Page' => 'content_management',
            'Media' => 'media_management',
            'Plugin' => 'plugin_management',
            'Setting' => 'system_configuration',
            default => 'admin_actions',
        };
    }

    /**
     * Sanitize value for logging (remove sensitive data).
     */
    private function sanitizeValue($value)
    {
        if (is_string($value) && strlen($value) > 1000) {
            return substr($value, 0, 1000) . '... (truncated)';
        }

        // Remove sensitive data patterns
        if (is_string($value)) {
            if (preg_match('/password|secret|key|token/i', $value)) {
                return '[REDACTED]';
            }
        }

        return $value;
    }

    /**
     * Sanitize model changes for logging.
     */
    private function sanitizeChanges(array $changes): array
    {
        $sensitiveFields = ['password', 'password_hash', 'remember_token', 'api_token', 'secret_key'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($changes[$field])) {
                $changes[$field] = '[REDACTED]';
            }
        }

        return $changes;
    }

    /**
     * Get audit trail for a user.
     */
    public function getUserAuditTrail(int $userId, int $limit = 100, int $offset = 0): array
    {
        if (!Config::get('cms.audit.store_in_database', true)) {
            return [];
        }

        return DB::table('audit_logs')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(function ($record) {
                $record->context = json_decode($record->context, true);
                return $record;
            })
            ->toArray();
    }

    /**
     * Get audit trail for a specific resource.
     */
    public function getResourceAuditTrail(string $category, string $resourceId = null, int $limit = 100): array
    {
        if (!Config::get('cms.audit.store_in_database', true)) {
            return [];
        }

        $query = DB::table('audit_logs')
            ->where('category', $category)
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($resourceId) {
            $query->whereJsonContains('context->resource_id', $resourceId);
        }

        return $query->get()
            ->map(function ($record) {
                $record->context = json_decode($record->context, true);
                return $record;
            })
            ->toArray();
    }

    /**
     * Get audit statistics.
     */
    public function getAuditStatistics(string $period = '30 days'): array
    {
        if (!Config::get('cms.audit.store_in_database', true)) {
            return [];
        }

        $since = now()->sub($period);

        return [
            'total_events' => DB::table('audit_logs')->where('created_at', '>=', $since)->count(),
            'by_category' => DB::table('audit_logs')
                ->where('created_at', '>=', $since)
                ->groupBy('category')
                ->selectRaw('category, count(*) as count')
                ->pluck('count', 'category')
                ->toArray(),
            'by_user' => DB::table('audit_logs')
                ->join('users', 'audit_logs.user_id', '=', 'users.id')
                ->where('audit_logs.created_at', '>=', $since)
                ->groupBy('users.id', 'users.email')
                ->selectRaw('users.email, count(*) as count')
                ->pluck('count', 'email')
                ->toArray(),
            'recent_events' => $this->getResourceAuditTrail('all', null, 10),
        ];
    }
}