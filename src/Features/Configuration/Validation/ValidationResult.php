<?php

namespace ArtisanPackUI\CMSFramework\Features\Configuration\Validation;

/**
 * Validation Result
 * 
 * Encapsulates the result of a configuration validation operation,
 * including success status, error messages, and detailed error information.
 */
class ValidationResult
{
    /**
     * Whether the validation passed
     */
    protected bool $valid;
    
    /**
     * Array of error messages
     */
    protected array $errors;
    
    /**
     * Detailed errors with field names as keys
     */
    protected array $detailedErrors;
    
    /**
     * Validation metadata
     */
    protected array $metadata;
    
    /**
     * Create a new validation result
     */
    public function __construct(
        bool $valid,
        array $errors = [],
        array $detailedErrors = [],
        array $metadata = []
    ) {
        $this->valid = $valid;
        $this->errors = $errors;
        $this->detailedErrors = $detailedErrors;
        $this->metadata = $metadata;
    }
    
    /**
     * Check if validation passed
     */
    public function isValid(): bool
    {
        return $this->valid;
    }
    
    /**
     * Check if validation failed
     */
    public function failed(): bool
    {
        return !$this->valid;
    }
    
    /**
     * Get all error messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Get detailed errors with field names
     */
    public function getDetailedErrors(): array
    {
        return $this->detailedErrors;
    }
    
    /**
     * Get errors for a specific field
     */
    public function getFieldErrors(string $field): array
    {
        return $this->detailedErrors[$field] ?? [];
    }
    
    /**
     * Check if a specific field has errors
     */
    public function hasFieldError(string $field): bool
    {
        return isset($this->detailedErrors[$field]) && !empty($this->detailedErrors[$field]);
    }
    
    /**
     * Get the first error message
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }
    
    /**
     * Get the number of errors
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }
    
    /**
     * Get validation metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
    
    /**
     * Get specific metadata value
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
    
    /**
     * Set metadata value
     */
    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }
    
    /**
     * Add an error message
     */
    public function addError(string $error): void
    {
        $this->errors[] = $error;
        $this->valid = false;
    }
    
    /**
     * Add a field-specific error
     */
    public function addFieldError(string $field, string $error): void
    {
        if (!isset($this->detailedErrors[$field])) {
            $this->detailedErrors[$field] = [];
        }
        
        $this->detailedErrors[$field][] = $error;
        $this->addError("{$field}: {$error}");
    }
    
    /**
     * Merge another validation result into this one
     */
    public function merge(ValidationResult $other): void
    {
        if ($other->failed()) {
            $this->valid = false;
            $this->errors = array_merge($this->errors, $other->getErrors());
            
            foreach ($other->getDetailedErrors() as $field => $fieldErrors) {
                if (!isset($this->detailedErrors[$field])) {
                    $this->detailedErrors[$field] = [];
                }
                $this->detailedErrors[$field] = array_merge($this->detailedErrors[$field], $fieldErrors);
            }
        }
        
        $this->metadata = array_merge($this->metadata, $other->getMetadata());
    }
    
    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'error_count' => $this->getErrorCount(),
            'errors' => $this->errors,
            'detailed_errors' => $this->detailedErrors,
            'metadata' => $this->metadata,
        ];
    }
    
    /**
     * Convert to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
    
    /**
     * Get a summary string of the validation result
     */
    public function getSummary(): string
    {
        if ($this->isValid()) {
            return "Validation passed successfully.";
        }
        
        $errorCount = $this->getErrorCount();
        $summary = "Validation failed with {$errorCount} error" . ($errorCount === 1 ? '' : 's') . ".";
        
        if ($errorCount > 0) {
            $summary .= " First error: " . $this->getFirstError();
        }
        
        return $summary;
    }
    
    /**
     * Get formatted error report
     */
    public function getFormattedReport(): string
    {
        if ($this->isValid()) {
            return "✅ Configuration validation passed successfully.\n";
        }
        
        $report = "❌ Configuration validation failed with " . $this->getErrorCount() . " error(s):\n\n";
        
        if (!empty($this->detailedErrors)) {
            $report .= "Field-specific errors:\n";
            foreach ($this->detailedErrors as $field => $fieldErrors) {
                $report .= "  {$field}:\n";
                foreach ($fieldErrors as $error) {
                    $report .= "    - {$error}\n";
                }
            }
            $report .= "\n";
        }
        
        if (!empty($this->errors)) {
            $report .= "All errors:\n";
            foreach ($this->errors as $index => $error) {
                $report .= ($index + 1) . ". {$error}\n";
            }
        }
        
        return $report;
    }
    
    /**
     * Create a successful validation result
     */
    public static function success(array $metadata = []): self
    {
        return new self(true, [], [], $metadata);
    }
    
    /**
     * Create a failed validation result
     */
    public static function failure(array $errors, array $detailedErrors = [], array $metadata = []): self
    {
        return new self(false, $errors, $detailedErrors, $metadata);
    }
    
    /**
     * Create validation result from Laravel validator
     */
    public static function fromValidator(\Illuminate\Validation\Validator $validator): self
    {
        if ($validator->passes()) {
            return self::success();
        }
        
        return new self(
            false,
            $validator->errors()->all(),
            $validator->errors()->toArray()
        );
    }
    
    /**
     * String representation of the validation result
     */
    public function __toString(): string
    {
        return $this->getSummary();
    }
}