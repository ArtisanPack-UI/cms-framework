<?php

declare(strict_types=1);

/**
 * Structured Logger Service
 *
 * Provides structured logging capabilities for the CMS framework with context support,
 * log enrichment, categorization, and integration with external monitoring services.
 * Handles all application logging with consistent formatting and metadata.
 *
 * @since 1.0.0
 * @author Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Services;

use ArtisanPackUI\CMSFramework\Exceptions\CMSException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Structured Logger Service
 *
 * Centralized logging service with structured context and metadata enrichment.
 */
class StructuredLoggerService
{
    /**
     * Default context data for all log entries.
     */
    private array $defaultContext = [];

    /**
     * Log categories configuration.
     */
    private array $categories = [
        'security' => ['level' => 'warning', 'always_report' => true],
        'authentication' => ['level' => 'info', 'always_report' => false],
        'authorization' => ['level' => 'warning', 'always_report' => true],
        'plugin' => ['level' => 'error', 'always_report' => false],
        'media' => ['level' => 'error', 'always_report' => false],
        'content' => ['level' => 'error', 'always_report' => false],
        'user' => ['level' => 'info', 'always_report' => false],
        'system' => ['level' => 'error', 'always_report' => true],
        'performance' => ['level' => 'warning', 'always_report' => false],
        'audit' => ['level' => 'info', 'always_report' => true],
    ];

    /**
     * Create a new structured logger instance.
     */
    public function __construct(?Request $request = null)
    {
        $this->initializeDefaultContext($request);
    }

    /**
     * Initialize default context data.
     */
    private function initializeDefaultContext(?Request $request): void
    {
        $this->defaultContext = [
            'application' => 'cms_framework',
            'version' => Config::get('app.version', '1.0.0'),
            'environment' => Config::get('app.env', 'production'),
            'timestamp' => now()->toISOString(),
            'request_id' => $request?->header('X-Request-ID') ?? uniqid('req_'),
            'session_id' => session()->getId(),
        ];

        if ($request) {
            $this->defaultContext['request'] = [
                'method' => $request->getMethod(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];
        }

        if (Auth::check()) {
            $user = Auth::user();
            $this->defaultContext['user'] = [
                'id' => $user->id,
                'email' => $user->email ?? null,
                'username' => $user->username ?? null,
            ];
        }
    }

    /**
     * Log an emergency message.
     */
    public function emergency(string $message, array $context = [], string $category = 'system'): void
    {
        $this->log('emergency', $message, $context, $category);
    }

    /**
     * Log an alert message.
     */
    public function alert(string $message, array $context = [], string $category = 'system'): void
    {
        $this->log('alert', $message, $context, $category);
    }

    /**
     * Log a critical message.
     */
    public function critical(string $message, array $context = [], string $category = 'system'): void
    {
        $this->log('critical', $message, $context, $category);
    }

    /**
     * Log an error message.
     */
    public function error(string $message, array $context = [], string $category = 'system'): void
    {
        $this->log('error', $message, $context, $category);
    }

    /**
     * Log a warning message.
     */
    public function warning(string $message, array $context = [], string $category = 'system'): void
    {
        $this->log('warning', $message, $context, $category);
    }

    /**
     * Log a notice message.
     */
    public function notice(string $message, array $context = [], string $category = 'system'): void
    {
        $this->log('notice', $message, $context, $category);
    }

    /**
     * Log an info message.
     */
    public function info(string $message, array $context = [], string $category = 'system'): void
    {
        $this->log('info', $message, $context, $category);
    }

    /**
     * Log a debug message.
     */
    public function debug(string $message, array $context = [], string $category = 'system'): void
    {
        $this->log('debug', $message, $context, $category);
    }

    /**
     * Log an exception with full context.
     */
    public function exception(Throwable $exception, array $additionalContext = [], string $category = 'system'): void
    {
        $context = array_merge($additionalContext, [
            'exception' => [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ],
        ]);

        // Add CMS exception specific data
        if ($exception instanceof CMSException) {
            $context['cms_exception'] = [
                'category' => $exception->getCategory(),
                'severity' => $exception->getSeverity(),
                'user_message' => $exception->getUserMessage(),
                'context' => $exception->getContext(),
                'reportable' => $exception->isReportable(),
            ];
            $category = $exception->getCategory();
        }

        $level = $this->getLogLevelForException($exception);
        $this->log($level, "Exception: {$exception->getMessage()}", $context, $category);
    }

    /**
     * Log a security event.
     */
    public function security(string $event, array $context = [], string $level = 'warning'): void
    {
        $enrichedContext = array_merge($context, [
            'security_event' => $event,
            'security_timestamp' => now()->toISOString(),
        ]);

        $this->log($level, "Security Event: {$event}", $enrichedContext, 'security');
    }

    /**
     * Log an authentication event.
     */
    public function authentication(string $event, int $userId, bool $success = true, array $context = []): void
    {
        $enrichedContext = array_merge($context, [
            'auth_event' => $event,
            'user_id' => $userId,
            'success' => $success,
            'auth_timestamp' => now()->toISOString(),
        ]);

        $level = $success ? 'info' : 'warning';
        $this->log($level, "Auth Event: {$event}", $enrichedContext, 'authentication');
    }

    /**
     * Log an authorization event.
     */
    public function authorization(string $event, int $userId, string $resource, string $action, bool $granted = false, array $context = []): void
    {
        $enrichedContext = array_merge($context, [
            'authz_event' => $event,
            'user_id' => $userId,
            'resource' => $resource,
            'action' => $action,
            'granted' => $granted,
            'authz_timestamp' => now()->toISOString(),
        ]);

        $level = $granted ? 'info' : 'warning';
        $this->log($level, "Authorization Event: {$event}", $enrichedContext, 'authorization');
    }

    /**
     * Log an audit event for sensitive operations.
     */
    public function audit(string $action, array $context = []): void
    {
        $enrichedContext = array_merge($context, [
            'audit_action' => $action,
            'audit_timestamp' => now()->toISOString(),
        ]);

        if (Auth::check()) {
            $enrichedContext['audit_user'] = [
                'id' => Auth::id(),
                'email' => Auth::user()->email ?? null,
                'ip_address' => request()?->ip(),
            ];
        }

        $this->log('info', "Audit: {$action}", $enrichedContext, 'audit');
    }

    /**
     * Log a performance metric.
     */
    public function performance(string $metric, float $value, string $unit = 'ms', array $context = []): void
    {
        $enrichedContext = array_merge($context, [
            'performance_metric' => $metric,
            'value' => $value,
            'unit' => $unit,
            'performance_timestamp' => now()->toISOString(),
        ]);

        $this->log('info', "Performance: {$metric} = {$value}{$unit}", $enrichedContext, 'performance');
    }

    /**
     * Log a structured message with full context.
     */
    private function log(string $level, string $message, array $context, string $category): void
    {
        $structuredContext = $this->buildStructuredContext($context, $category, $level);
        
        Log::log($level, $message, $structuredContext);
        
        // Send to external monitoring if configured and required
        $this->sendToExternalMonitoring($level, $message, $structuredContext, $category);
    }

    /**
     * Build structured context with metadata.
     */
    private function buildStructuredContext(array $context, string $category, string $level): array
    {
        return [
            'level' => $level,
            'category' => $category,
            'context' => $context,
            'metadata' => $this->defaultContext,
            'enrichment' => [
                'memory_usage' => memory_get_peak_usage(true),
                'execution_time' => microtime(true) - LARAVEL_START,
                'process_id' => getmypid(),
            ],
        ];
    }

    /**
     * Determine log level for exception.
     */
    private function getLogLevelForException(Throwable $exception): string
    {
        if ($exception instanceof CMSException) {
            return match($exception->getSeverity()) {
                'critical' => 'critical',
                'warning' => 'warning',
                'info' => 'info',
                default => 'error',
            };
        }

        return 'error';
    }

    /**
     * Send log to external monitoring service.
     */
    private function sendToExternalMonitoring(string $level, string $message, array $context, string $category): void
    {
        $categoryConfig = $this->categories[$category] ?? ['always_report' => false];
        
        $shouldReport = $categoryConfig['always_report'] || 
                       in_array($level, ['emergency', 'alert', 'critical', 'error']) ||
                       Config::get('logging.external_monitoring.enabled', false);

        if (!$shouldReport) {
            return;
        }

        try {
            // This would integrate with services like Sentry, New Relic, etc.
            // For now, we'll just prepare the data structure
            $monitoringData = [
                'level' => $level,
                'message' => $message,
                'context' => $context,
                'category' => $category,
                'timestamp' => now()->toISOString(),
                'environment' => Config::get('app.env'),
                'application' => 'cms_framework',
            ];

            // Future: Send to configured monitoring service
            // MonitoringService::send($monitoringData);
            
        } catch (Throwable $e) {
            // Fail silently to avoid logging loops
            Log::error('Failed to send log to external monitoring', [
                'error' => $e->getMessage(),
                'original_message' => $message,
            ]);
        }
    }

    /**
     * Add persistent context that will be included in all logs.
     */
    public function addPersistentContext(string $key, mixed $value): void
    {
        $this->defaultContext[$key] = $value;
    }

    /**
     * Remove persistent context.
     */
    public function removePersistentContext(string $key): void
    {
        unset($this->defaultContext[$key]);
    }

    /**
     * Get current context.
     */
    public function getContext(): array
    {
        return $this->defaultContext;
    }

    /**
     * Create a child logger with additional context.
     */
    public function withContext(array $context): static
    {
        $clone = clone $this;
        $clone->defaultContext = array_merge($this->defaultContext, $context);
        return $clone;
    }
}