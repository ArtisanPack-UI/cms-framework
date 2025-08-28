<?php

namespace ArtisanPackUI\CMSFramework\Services;

use ArtisanPackUI\CMSFramework\Contracts\APMProviderInterface;
use ArtisanPackUI\CMSFramework\Models\ErrorLog;
use ArtisanPackUI\CMSFramework\Models\PerformanceMetric;
use ArtisanPackUI\CMSFramework\Models\PerformanceTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use TorMorten\Eventy\Facades\Eventy;

/**
 * APMService.
 *
 * Core service for Application Performance Monitoring functionality.
 * Coordinates multiple APM providers and provides unified interface
 * for metrics collection, error tracking, and performance monitoring.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Services
 * @since   1.3.0
 */
class APMService
{
    /**
     * Active APM providers.
     *
     * @var array<string, APMProviderInterface>
     */
    protected array $providers = [];

    /**
     * Active transaction IDs.
     *
     * @var array<string, array>
     */
    protected array $activeTransactions = [];

    /**
     * Metrics buffer for batch processing.
     *
     * @var array
     */
    protected array $metricsBuffer = [];

    /**
     * Cache service instance.
     *
     * @var CacheService
     */
    protected CacheService $cacheService;

    /**
     * Create a new APMService instance.
     *
     * @since 1.3.0
     *
     * @param CacheService $cacheService
     */
    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Register an APM provider.
     *
     * @since 1.3.0
     *
     * @param string $name Provider name
     * @param APMProviderInterface $provider Provider instance
     * @return void
     */
    public function registerProvider(string $name, APMProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;

        // Allow filtering of provider registration through Eventy hooks
        Eventy::action('ap.cms.apm.provider_registered', $name, $provider);
    }

    /**
     * Get a registered APM provider.
     *
     * @since 1.3.0
     *
     * @param string $name Provider name
     * @return APMProviderInterface|null
     */
    public function getProvider(string $name): ?APMProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * Get all registered providers.
     *
     * @since 1.3.0
     *
     * @return array<string, APMProviderInterface>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Check if APM is enabled and has active providers.
     *
     * @since 1.3.0
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        if (!config('cms.apm.enabled', true)) {
            return false;
        }

        return !empty(array_filter($this->providers, fn($provider) => $provider->isEnabled()));
    }

    /**
     * Track a custom metric.
     *
     * @since 1.3.0
     *
     * @param string $name Metric name
     * @param float $value Metric value
     * @param array $tags Optional tags
     * @param string $unit Optional unit (default: 'ms')
     * @return void
     */
    public function trackMetric(string $name, float $value, array $tags = [], string $unit = 'ms'): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        // Apply sampling
        $sampleRate = config('cms.apm.metrics.sample_rate', 1.0);
        if ($sampleRate < 1.0 && mt_rand() / mt_getrandmax() > $sampleRate) {
            return;
        }

        // Allow filtering through Eventy hooks
        $filtered = Eventy::filter('ap.cms.apm.track_metric', [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'unit' => $unit,
        ]);

        if ($filtered === false) {
            return;
        }

        // Store in internal database if enabled
        if (config('cms.apm.providers.internal.enabled', true)) {
            $this->storeMetric($filtered['name'], $filtered['value'], $filtered['tags'], $filtered['unit']);
        }

        // Send to external providers
        foreach ($this->providers as $provider) {
            if ($provider->isEnabled()) {
                try {
                    $provider->trackMetric($filtered['name'], $filtered['value'], $filtered['tags']);
                } catch (\Throwable $e) {
                    Log::warning('APM provider failed to track metric', [
                        'provider' => $provider->getProviderName(),
                        'metric' => $filtered['name'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Eventy::action('ap.cms.apm.metric_tracked', $filtered);
    }

    /**
     * Start a performance transaction.
     *
     * @since 1.3.0
     *
     * @param string $name Transaction name
     * @param array $metadata Optional metadata
     * @return string Transaction ID
     */
    public function startTransaction(string $name, array $metadata = []): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        $transactionId = uniqid('txn_', true);
        
        $transaction = [
            'id' => $transactionId,
            'name' => $name,
            'started_at' => microtime(true),
            'memory_start' => memory_get_usage(true),
            'db_query_count_start' => 0, // Will be set by database listener
            'metadata' => $metadata,
        ];

        $this->activeTransactions[$transactionId] = $transaction;

        // Store in database if internal provider is enabled
        if (config('cms.apm.providers.internal.enabled', true)) {
            $this->storeTransactionStart($transaction);
        }

        // Start transaction in external providers
        foreach ($this->providers as $provider) {
            if ($provider->isEnabled()) {
                try {
                    $provider->startTransaction($name);
                } catch (\Throwable $e) {
                    Log::warning('APM provider failed to start transaction', [
                        'provider' => $provider->getProviderName(),
                        'transaction' => $name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Eventy::action('ap.cms.apm.transaction_started', $transactionId, $name, $metadata);

        return $transactionId;
    }

    /**
     * End a performance transaction.
     *
     * @since 1.3.0
     *
     * @param string $transactionId Transaction ID
     * @param array $additionalMetadata Optional additional metadata
     * @return void
     */
    public function endTransaction(string $transactionId, array $additionalMetadata = []): void
    {
        if (!$this->isEnabled() || !isset($this->activeTransactions[$transactionId])) {
            return;
        }

        $transaction = $this->activeTransactions[$transactionId];
        $endTime = microtime(true);
        
        $transaction['completed_at'] = $endTime;
        $transaction['duration_ms'] = ($endTime - $transaction['started_at']) * 1000;
        $transaction['memory_usage_mb'] = (memory_get_usage(true) - $transaction['memory_start']) / 1024 / 1024;
        $transaction['metadata'] = array_merge($transaction['metadata'], $additionalMetadata);

        // Store in database if internal provider is enabled
        if (config('cms.apm.providers.internal.enabled', true)) {
            $this->storeTransactionEnd($transaction);
        }

        // End transaction in external providers
        foreach ($this->providers as $provider) {
            if ($provider->isEnabled()) {
                try {
                    $provider->endTransaction($transactionId);
                } catch (\Throwable $e) {
                    Log::warning('APM provider failed to end transaction', [
                        'provider' => $provider->getProviderName(),
                        'transaction_id' => $transactionId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Eventy::action('ap.cms.apm.transaction_ended', $transactionId, $transaction);

        unset($this->activeTransactions[$transactionId]);
    }

    /**
     * Record an error or exception.
     *
     * @since 1.3.0
     *
     * @param \Throwable $exception Exception to record
     * @param array $context Additional context
     * @return void
     */
    public function recordError(\Throwable $exception, array $context = []): void
    {
        if (!$this->isEnabled() || !config('cms.apm.error_tracking.enabled', true)) {
            return;
        }

        // Check if this exception type should be ignored
        $ignoreExceptions = config('cms.apm.error_tracking.ignore_exceptions', []);
        if (in_array(get_class($exception), $ignoreExceptions)) {
            return;
        }

        // Store in internal database if enabled
        if (config('cms.apm.providers.internal.enabled', true)) {
            try {
                ErrorLog::createOrUpdate($exception, $context);
            } catch (\Throwable $e) {
                Log::error('Failed to store error in internal database', [
                    'error' => $e->getMessage(),
                    'original_exception' => get_class($exception),
                ]);
            }
        }

        // Send to external providers
        foreach ($this->providers as $provider) {
            if ($provider->isEnabled()) {
                try {
                    $provider->recordError($exception, $context);
                } catch (\Throwable $e) {
                    Log::warning('APM provider failed to record error', [
                        'provider' => $provider->getProviderName(),
                        'exception' => get_class($exception),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Eventy::action('ap.cms.apm.error_recorded', $exception, $context);
    }

    /**
     * Record a custom event.
     *
     * @since 1.3.0
     *
     * @param string $eventType Event type/name
     * @param array $attributes Event attributes
     * @return void
     */
    public function recordCustomEvent(string $eventType, array $attributes): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        // Send to external providers
        foreach ($this->providers as $provider) {
            if ($provider->isEnabled()) {
                try {
                    $provider->recordCustomEvent($eventType, $attributes);
                } catch (\Throwable $e) {
                    Log::warning('APM provider failed to record custom event', [
                        'provider' => $provider->getProviderName(),
                        'event_type' => $eventType,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Eventy::action('ap.cms.apm.custom_event_recorded', $eventType, $attributes);
    }

    /**
     * Set user context for tracking.
     *
     * @since 1.3.0
     *
     * @param int|string $userId User identifier
     * @param array $userAttributes Additional user attributes
     * @return void
     */
    public function setUser($userId, array $userAttributes = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        foreach ($this->providers as $provider) {
            if ($provider->isEnabled()) {
                try {
                    $provider->setUser($userId, $userAttributes);
                } catch (\Throwable $e) {
                    Log::warning('APM provider failed to set user context', [
                        'provider' => $provider->getProviderName(),
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Eventy::action('ap.cms.apm.user_context_set', $userId, $userAttributes);
    }

    /**
     * Add custom attributes to current transaction.
     *
     * @since 1.3.0
     *
     * @param array $attributes Attributes to add
     * @return void
     */
    public function addCustomAttributes(array $attributes): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        foreach ($this->providers as $provider) {
            if ($provider->isEnabled()) {
                try {
                    $provider->addCustomAttributes($attributes);
                } catch (\Throwable $e) {
                    Log::warning('APM provider failed to add custom attributes', [
                        'provider' => $provider->getProviderName(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Eventy::action('ap.cms.apm.custom_attributes_added', $attributes);
    }

    /**
     * Flush all pending metrics and data.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function flush(): void
    {
        foreach ($this->providers as $provider) {
            if ($provider->isEnabled()) {
                try {
                    $provider->flush();
                } catch (\Throwable $e) {
                    Log::warning('APM provider failed to flush', [
                        'provider' => $provider->getProviderName(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Eventy::action('ap.cms.apm.flushed');
    }

    /**
     * Get health status of all providers.
     *
     * @since 1.3.0
     *
     * @return array
     */
    public function getHealthStatus(): array
    {
        $status = [
            'apm_enabled' => $this->isEnabled(),
            'providers' => [],
        ];

        foreach ($this->providers as $name => $provider) {
            try {
                $status['providers'][$name] = [
                    'enabled' => $provider->isEnabled(),
                    'health' => $provider->getHealthStatus(),
                ];
            } catch (\Throwable $e) {
                $status['providers'][$name] = [
                    'enabled' => false,
                    'health' => [
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ],
                ];
            }
        }

        return Eventy::filter('ap.cms.apm.health_status', $status);
    }

    /**
     * Store metric in internal database.
     *
     * @since 1.3.0
     *
     * @param string $name
     * @param float $value
     * @param array $tags
     * @param string $unit
     * @return void
     */
    protected function storeMetric(string $name, float $value, array $tags, string $unit): void
    {
        try {
            PerformanceMetric::create([
                'metric_name' => $name,
                'metric_value' => $value,
                'metric_unit' => $unit,
                'tags' => $tags,
                'recorded_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to store metric in database', [
                'metric' => $name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Store transaction start in internal database.
     *
     * @since 1.3.0
     *
     * @param array $transaction
     * @return void
     */
    protected function storeTransactionStart(array $transaction): void
    {
        try {
            PerformanceTransaction::create([
                'transaction_id' => $transaction['id'],
                'transaction_name' => $transaction['name'],
                'started_at' => now(),
                'metadata' => $transaction['metadata'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to store transaction start in database', [
                'transaction_id' => $transaction['id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Store transaction end in internal database.
     *
     * @since 1.3.0
     *
     * @param array $transaction
     * @return void
     */
    protected function storeTransactionEnd(array $transaction): void
    {
        try {
            PerformanceTransaction::where('transaction_id', $transaction['id'])
                ->update([
                    'duration_ms' => $transaction['duration_ms'],
                    'memory_usage_mb' => $transaction['memory_usage_mb'],
                    'completed_at' => now(),
                    'metadata' => $transaction['metadata'],
                ]);
        } catch (\Throwable $e) {
            Log::error('Failed to store transaction end in database', [
                'transaction_id' => $transaction['id'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}