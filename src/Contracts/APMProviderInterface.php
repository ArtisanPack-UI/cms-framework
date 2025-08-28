<?php

namespace ArtisanPackUI\CMSFramework\Contracts;

/**
 * APMProviderInterface.
 *
 * Interface for Application Performance Monitoring providers.
 * Defines the contract for integrating with various APM services like New Relic,
 * DataDog, Sentry, or custom internal monitoring solutions.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Contracts
 * @since   1.3.0
 */
interface APMProviderInterface
{
    /**
     * Track a custom metric with optional tags.
     *
     * @since 1.3.0
     *
     * @param string $name Metric name (e.g., 'response_time', 'memory_usage')
     * @param float $value Metric value
     * @param array $tags Optional tags for metric categorization
     * @return void
     */
    public function trackMetric(string $name, float $value, array $tags = []): void;

    /**
     * Start a performance transaction.
     *
     * @since 1.3.0
     *
     * @param string $name Transaction name (e.g., 'api.content.index', 'web.home')
     * @return string Transaction ID for later reference
     */
    public function startTransaction(string $name): string;

    /**
     * End a performance transaction.
     *
     * @since 1.3.0
     *
     * @param string $transactionId Transaction ID returned by startTransaction()
     * @return void
     */
    public function endTransaction(string $transactionId): void;

    /**
     * Record an error or exception.
     *
     * @since 1.3.0
     *
     * @param \Throwable $exception The exception to record
     * @param array $context Additional context data
     * @return void
     */
    public function recordError(\Throwable $exception, array $context = []): void;

    /**
     * Record a custom event with attributes.
     *
     * @since 1.3.0
     *
     * @param string $eventType Event type/name
     * @param array $attributes Event attributes
     * @return void
     */
    public function recordCustomEvent(string $eventType, array $attributes): void;

    /**
     * Add custom attributes to the current transaction.
     *
     * @since 1.3.0
     *
     * @param array $attributes Key-value pairs to add as attributes
     * @return void
     */
    public function addCustomAttributes(array $attributes): void;

    /**
     * Set the user context for tracking.
     *
     * @since 1.3.0
     *
     * @param int|string $userId User identifier
     * @param array $userAttributes Additional user attributes
     * @return void
     */
    public function setUser($userId, array $userAttributes = []): void;

    /**
     * Check if the APM provider is enabled and functional.
     *
     * @since 1.3.0
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Get the name/identifier of this APM provider.
     *
     * @since 1.3.0
     *
     * @return string
     */
    public function getProviderName(): string;

    /**
     * Flush any pending metrics or data.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function flush(): void;

    /**
     * Get health status of the APM provider.
     *
     * @since 1.3.0
     *
     * @return array Health status information
     */
    public function getHealthStatus(): array;
}