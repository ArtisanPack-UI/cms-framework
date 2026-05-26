<?php

declare( strict_types=1 );

/**
 * Validation exception for the CMS Framework.
 *
 * Thrown when validation fails for user input or data.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Exceptions;

/**
 * Exception thrown when validation fails.
 *
 * Renders as:
 * {
 *   "error": {
 *     "code": "VALIDATION_ERROR",
 *     "message": "The given data was invalid.",
 *     "errors": {
 *       "title": ["The title field is required."]
 *     }
 *   }
 * }
 *
 * @since 1.0.0
 */
class ValidationException extends CMSFrameworkException
{
    /**
     * The machine-readable error code for this exception.
     *
     * @since 1.1.0
     */
    protected string $errorCode = 'VALIDATION_ERROR';

    /**
     * The HTTP status code for this exception.
     *
     * @since 1.1.0
     */
    protected int $statusCode = 422;

    /**
     * The validation errors.
     *
     * @var array<string, array<string>>
     */
    protected array $errors = [];

    /**
     * Create a new validation exception with errors.
     *
     * @param  string  $message  The exception message.
     * @param  array<string, array<string>>  $errors  The validation errors.
     *
     * @since 1.0.0
     */
    public static function withErrors( string $message, array $errors ): self
    {
        $exception         = new self( $message );
        $exception->errors = $errors;

        return $exception;
    }

    /**
     * Get the validation errors.
     *
     * @return array<string, array<string>>
     *
     * @since 1.0.0
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if a specific field has errors.
     *
     * @param  string  $field  The field name.
     *
     * @since 1.0.0
     */
    public function hasError( string $field ): bool
    {
        return isset( $this->errors[ $field ] );
    }

    /**
     * Build the error response payload including field-level validation errors.
     *
     * @since 1.1.0
     *
     * @return array{error: array{code: string, message: string, errors?: array<string, array<string>>}}
     */
    protected function buildErrorPayload(): array
    {
        $payload = parent::buildErrorPayload();

        if ( ! empty( $this->errors ) ) {
            $payload['error']['errors'] = $this->errors;
        }

        return $payload;
    }
}
