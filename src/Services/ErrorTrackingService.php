<?php

declare(strict_types=1);

/**
 * Error Tracking Service
 *
 * Provides integration with external error monitoring services like Sentry, Bugsnag,
 * and other error tracking platforms. Handles error reporting, user context,
 * release tracking, and performance monitoring for the CMS framework.
 *
 * @since 1.0.0
 * @author Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Services;

use ArtisanPackUI\CMSFramework\Exceptions\CMSException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Error Tracking Service
 *
 * Centralized service for external error monitoring integration.
 */
class ErrorTrackingService
{
    /**
     * Error tracking client instance (could be Sentry, Bugsnag, etc.)
     */
    private mixed $client = null;

    /**
     * Service configuration.
     */
    private array $config;

    /**
     * Request context.
     */
    private ?Request $request;

    /**
     * Create a new error tracking service instance.
     */
    public function __construct(?Request $request = null)
    {
        $this->config = Config::get('cms.error_tracking', []);
        $this->request = $request ?? request();
        $this->initializeClient();
    }

    /**
     * Initialize the error tracking client.
     */
    private function initializeClient(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $provider = $this->config['provider'] ?? 'sentry';
        
        try {
            match ($provider) {
                'sentry' => $this->initializeSentryClient(),
                'bugsnag' => $this->initializeBugsnagClient(),
                'custom' => $this->initializeCustomClient(),
                default => null,
            };
        } catch (Throwable $e) {
            // Fail silently to avoid loops
            error_log("Failed to initialize error tracking client: " . $e->getMessage());
        }
    }

    /**
     * Initialize Sentry client.
     */
    private function initializeSentryClient(): void
    {
        if (!function_exists('\\Sentry\\init')) {
            return;
        }

        \Sentry\init([
            'dsn' => $this->config['sentry']['dsn'] ?? null,
            'environment' => $this->config['environment'] ?? Config::get('app.env'),
            'release' => $this->config['release'] ?? Config::get('app.version'),
            'sample_rate' => $this->config['sentry']['sample_rate'] ?? 1.0,
            'traces_sample_rate' => $this->config['sentry']['traces_sample_rate'] ?? 0.1,
            'before_send' => [$this, 'beforeSendFilter'],
            'before_send_transaction' => [$this, 'beforeSendTransactionFilter'],
        ]);

        $this->client = 'sentry';
    }

    /**
     * Initialize Bugsnag client.
     */
    private function initializeBugsnagClient(): void
    {
        if (!class_exists('\\Bugsnag\\Client')) {
            return;
        }

        // Bugsnag initialization would go here
        // This is a placeholder for Bugsnag integration
        $this->client = 'bugsnag';
    }

    /**
     * Initialize custom client.
     */
    private function initializeCustomClient(): void
    {
        $clientClass = $this->config['custom']['client_class'] ?? null;
        
        if ($clientClass && class_exists($clientClass)) {
            $this->client = new $clientClass($this->config['custom'] ?? []);
        }
    }

    /**
     * Check if error tracking is enabled.
     */
    public function isEnabled(): bool
    {
        return ($this->config['enabled'] ?? false) && 
               !Config::get('app.debug', false) && 
               Config::get('app.env') !== 'testing';
    }

    /**
     * Report an exception to the error tracking service.
     */
    public function reportException(Throwable $exception, array $context = []): ?string
    {
        if (!$this->isEnabled() || !$this->shouldReport($exception)) {
            return null;
        }

        try {
            return match ($this->client) {
                'sentry' => $this->reportToSentry($exception, $context),
                'bugsnag' => $this->reportToBugsnag($exception, $context),
                default => $this->reportToCustomClient($exception, $context),
            };
        } catch (Throwable $e) {
            error_log("Failed to report exception to error tracking service: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Report exception to Sentry.
     */
    private function reportToSentry(Throwable $exception, array $context): ?string
    {
        if (!function_exists('\\Sentry\\captureException')) {
            return null;
        }

        $this->configureSentryScope($context);
        
        $eventId = \Sentry\captureException($exception);
        
        return $eventId ? (string) $eventId : null;
    }

    /**
     * Report exception to Bugsnag.
     */
    private function reportToBugsnag(Throwable $exception, array $context): ?string
    {
        // Placeholder for Bugsnag reporting
        return null;
    }

    /**
     * Report exception to custom client.
     */
    private function reportToCustomClient(Throwable $exception, array $context): ?string
    {
        if (is_object($this->client) && method_exists($this->client, 'reportException')) {
            return $this->client->reportException($exception, $context);
        }
        
        return null;
    }

    /**
     * Configure Sentry scope with context data.
     */
    private function configureSentryScope(array $context): void
    {
        if (!function_exists('\\Sentry\\configureScope')) {
            return;
        }

        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($context): void {
            // Set user context
            if (Auth::check()) {
                $user = Auth::user();
                $scope->setUser([
                    'id' => $user->id,
                    'email' => $user->email ?? null,
                    'username' => $user->username ?? null,
                    'role' => $user->role?->name ?? null,
                ]);
            }

            // Set request context
            if ($this->request) {
                $scope->setTag('request.method', $this->request->getMethod());
                $scope->setTag('request.url', $this->request->fullUrl());
                $scope->setContext('request', [
                    'method' => $this->request->getMethod(),
                    'url' => $this->request->fullUrl(),
                    'ip' => $this->request->ip(),
                    'user_agent' => $this->request->userAgent(),
                    'headers' => $this->sanitizeHeaders($this->request->headers->all()),
                ]);
            }

            // Set additional context
            foreach ($context as $key => $value) {
                if (is_array($value)) {
                    $scope->setContext($key, $value);
                } else {
                    $scope->setExtra($key, $value);
                }
            }

            // Set framework context
            $scope->setTag('framework', 'laravel');
            $scope->setTag('cms_framework', 'artisanpack-ui');
            $scope->setContext('application', [
                'name' => Config::get('app.name'),
                'version' => Config::get('app.version'),
                'environment' => Config::get('app.env'),
                'debug' => Config::get('app.debug'),
            ]);
        });
    }

    /**
     * Determine if exception should be reported.
     */
    private function shouldReport(Throwable $exception): bool
    {
        // Check if exception is in ignore list
        $ignoreTypes = $this->config['ignore_exceptions'] ?? [
            \Illuminate\Http\Exceptions\HttpResponseException::class,
            \Illuminate\Validation\ValidationException::class,
            \Illuminate\Auth\AuthenticationException::class,
        ];

        foreach ($ignoreTypes as $ignoreType) {
            if ($exception instanceof $ignoreType) {
                return false;
            }
        }

        // Check CMS exception reportable status
        if ($exception instanceof CMSException) {
            return $exception->isReportable();
        }

        // Report by default
        return true;
    }

    /**
     * Add breadcrumb for tracking user actions.
     */
    public function addBreadcrumb(string $message, string $category = 'default', string $level = 'info', array $data = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            match ($this->client) {
                'sentry' => $this->addSentryBreadcrumb($message, $category, $level, $data),
                'bugsnag' => $this->addBugsnagBreadcrumb($message, $category, $level, $data),
                default => $this->addCustomBreadcrumb($message, $category, $level, $data),
            };
        } catch (Throwable $e) {
            // Fail silently
        }
    }

    /**
     * Add breadcrumb to Sentry.
     */
    private function addSentryBreadcrumb(string $message, string $category, string $level, array $data): void
    {
        if (!function_exists('\\Sentry\\addBreadcrumb')) {
            return;
        }

        \Sentry\addBreadcrumb([
            'message' => $message,
            'category' => $category,
            'level' => $level,
            'data' => $data,
            'timestamp' => time(),
        ]);
    }

    /**
     * Add breadcrumb to Bugsnag.
     */
    private function addBugsnagBreadcrumb(string $message, string $category, string $level, array $data): void
    {
        // Placeholder for Bugsnag breadcrumb
    }

    /**
     * Add breadcrumb to custom client.
     */
    private function addCustomBreadcrumb(string $message, string $category, string $level, array $data): void
    {
        if (is_object($this->client) && method_exists($this->client, 'addBreadcrumb')) {
            $this->client->addBreadcrumb($message, $category, $level, $data);
        }
    }

    /**
     * Set user context for error tracking.
     */
    public function setUserContext(?int $userId = null, array $userData = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $user = $userId ? \App\Models\User::find($userId) : Auth::user();
        
        if (!$user) {
            return;
        }

        $userContext = array_merge([
            'id' => $user->id,
            'email' => $user->email ?? null,
            'username' => $user->username ?? null,
            'role' => $user->role?->name ?? null,
        ], $userData);

        try {
            match ($this->client) {
                'sentry' => $this->setSentryUserContext($userContext),
                'bugsnag' => $this->setBugsnagUserContext($userContext),
                default => $this->setCustomUserContext($userContext),
            };
        } catch (Throwable $e) {
            // Fail silently
        }
    }

    /**
     * Set user context for Sentry.
     */
    private function setSentryUserContext(array $userContext): void
    {
        if (!function_exists('\\Sentry\\configureScope')) {
            return;
        }

        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($userContext): void {
            $scope->setUser($userContext);
        });
    }

    /**
     * Set user context for Bugsnag.
     */
    private function setBugsnagUserContext(array $userContext): void
    {
        // Placeholder for Bugsnag user context
    }

    /**
     * Set user context for custom client.
     */
    private function setCustomUserContext(array $userContext): void
    {
        if (is_object($this->client) && method_exists($this->client, 'setUserContext')) {
            $this->client->setUserContext($userContext);
        }
    }

    /**
     * Set tags for error categorization.
     */
    public function setTags(array $tags): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            match ($this->client) {
                'sentry' => $this->setSentryTags($tags),
                'bugsnag' => $this->setBugsnagTags($tags),
                default => $this->setCustomTags($tags),
            };
        } catch (Throwable $e) {
            // Fail silently
        }
    }

    /**
     * Set tags for Sentry.
     */
    private function setSentryTags(array $tags): void
    {
        if (!function_exists('\\Sentry\\configureScope')) {
            return;
        }

        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($tags): void {
            foreach ($tags as $key => $value) {
                $scope->setTag($key, (string) $value);
            }
        });
    }

    /**
     * Set tags for Bugsnag.
     */
    private function setBugsnagTags(array $tags): void
    {
        // Placeholder for Bugsnag tags
    }

    /**
     * Set tags for custom client.
     */
    private function setCustomTags(array $tags): void
    {
        if (is_object($this->client) && method_exists($this->client, 'setTags')) {
            $this->client->setTags($tags);
        }
    }

    /**
     * Capture a custom message.
     */
    public function captureMessage(string $message, string $level = 'info', array $context = []): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            return match ($this->client) {
                'sentry' => $this->captureSentryMessage($message, $level, $context),
                'bugsnag' => $this->captureBugsnagMessage($message, $level, $context),
                default => $this->captureCustomMessage($message, $level, $context),
            };
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Capture message in Sentry.
     */
    private function captureSentryMessage(string $message, string $level, array $context): ?string
    {
        if (!function_exists('\\Sentry\\captureMessage')) {
            return null;
        }

        $this->configureSentryScope($context);
        $eventId = \Sentry\captureMessage($message, $level);
        
        return $eventId ? (string) $eventId : null;
    }

    /**
     * Capture message in Bugsnag.
     */
    private function captureBugsnagMessage(string $message, string $level, array $context): ?string
    {
        // Placeholder for Bugsnag message capture
        return null;
    }

    /**
     * Capture message in custom client.
     */
    private function captureCustomMessage(string $message, string $level, array $context): ?string
    {
        if (is_object($this->client) && method_exists($this->client, 'captureMessage')) {
            return $this->client->captureMessage($message, $level, $context);
        }
        
        return null;
    }

    /**
     * Before send filter for Sentry.
     */
    public function beforeSendFilter(\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event
    {
        // Apply custom filtering logic
        $filterRules = $this->config['filters'] ?? [];
        
        foreach ($filterRules as $rule) {
            if ($this->applyFilter($event, $rule)) {
                return null; // Filter out the event
            }
        }
        
        return $event;
    }

    /**
     * Before send transaction filter for Sentry.
     */
    public function beforeSendTransactionFilter(\Sentry\Event $event): ?\Sentry\Event
    {
        // Apply performance monitoring filters
        return $event;
    }

    /**
     * Apply a filter rule to an event.
     */
    private function applyFilter(\Sentry\Event $event, array $rule): bool
    {
        // Implement custom filtering logic based on rule
        return false;
    }

    /**
     * Sanitize headers for reporting.
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];
        
        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['[REDACTED]'];
            }
        }
        
        return $headers;
    }

    /**
     * Get error tracking configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Test the error tracking integration.
     */
    public function testIntegration(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $eventId = $this->captureMessage('Error tracking test message', 'info', [
                'test' => true,
                'timestamp' => now()->toISOString(),
            ]);
            
            return $eventId !== null;
        } catch (Throwable $e) {
            return false;
        }
    }
}