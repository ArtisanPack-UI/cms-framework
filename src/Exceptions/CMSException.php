<?php

declare(strict_types=1);

/**
 * Base CMS Exception
 *
 * Base exception class for all CMS framework-specific errors.
 * Provides structured error handling with context, error codes,
 * and detailed error information for debugging and monitoring.
 *
 * @since 1.0.0
 * @author Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Exceptions;

use Exception;
use Throwable;

/**
 * Base CMS Exception Class
 *
 * Provides enhanced exception handling with context, error codes,
 * and structured error information for the CMS framework.
 */
class CMSException extends Exception
{
    /**
     * Error context data.
     */
    protected array $context = [];

    /**
     * Error category/type.
     */
    protected string $category = 'general';

    /**
     * Whether this error should be reported to external services.
     */
    protected bool $reportable = true;

    /**
     * Error severity level.
     */
    protected string $severity = 'error';

    /**
     * User-friendly error message.
     */
    protected ?string $userMessage = null;

    /**
     * Create a new CMS exception.
     *
     * @param string $message The internal error message
     * @param int $code The error code
     * @param Throwable|null $previous Previous exception
     * @param array $context Additional context data
     * @param string|null $userMessage User-friendly message
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
        ?string $userMessage = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->context = $context;
        $this->userMessage = $userMessage;
    }

    /**
     * Get error context data.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set error context data.
     */
    public function setContext(array $context): static
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Add context data.
     */
    public function addContext(string $key, mixed $value): static
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Get error category.
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Set error category.
     */
    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    /**
     * Check if error should be reported.
     */
    public function isReportable(): bool
    {
        return $this->reportable;
    }

    /**
     * Set reportable status.
     */
    public function setReportable(bool $reportable): static
    {
        $this->reportable = $reportable;
        return $this;
    }

    /**
     * Get error severity level.
     */
    public function getSeverity(): string
    {
        return $this->severity;
    }

    /**
     * Set error severity level.
     */
    public function setSeverity(string $severity): static
    {
        $this->severity = $severity;
        return $this;
    }

    /**
     * Get user-friendly error message.
     */
    public function getUserMessage(): ?string
    {
        return $this->userMessage;
    }

    /**
     * Set user-friendly error message.
     */
    public function setUserMessage(?string $userMessage): static
    {
        $this->userMessage = $userMessage;
        return $this;
    }

    /**
     * Get structured error data for logging and reporting.
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'user_message' => $this->getUserMessage(),
            'code' => $this->getCode(),
            'category' => $this->getCategory(),
            'severity' => $this->getSeverity(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->getContext(),
            'trace' => $this->getTrace(),
            'reportable' => $this->isReportable(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Convert to JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Create exception with fluent interface.
     */
    public static function create(string $message, int $code = 0): static
    {
        return new static($message, $code);
    }

    /**
     * Create exception from another exception.
     */
    public static function fromException(Throwable $exception, ?string $userMessage = null): static
    {
        return new static(
            $exception->getMessage(),
            $exception->getCode(),
            $exception,
            [],
            $userMessage
        );
    }
}