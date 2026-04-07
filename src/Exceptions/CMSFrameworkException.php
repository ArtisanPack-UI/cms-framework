<?php

declare( strict_types=1 );

/**
 * Base exception for the CMS Framework.
 *
 * All framework-specific exceptions should extend this class to provide a consistent
 * exception hierarchy and allow for catch-all exception handling when needed.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Base exception class for all CMS Framework exceptions.
 *
 * This class provides a foundation for all framework-specific exceptions,
 * allowing applications to catch all framework exceptions if needed while
 * still maintaining granular exception handling for specific cases.
 *
 * All exceptions render as a consistent JSON format when thrown during
 * API requests:
 *
 * {
 *   "error": {
 *     "code": "SERVER_ERROR",
 *     "message": "Something went wrong."
 *   }
 * }
 *
 * @since 1.0.0
 */
class CMSFrameworkException extends Exception
{
    /**
     * The machine-readable error code for this exception.
     *
     * @since 1.1.0
     */
    protected string $errorCode = 'SERVER_ERROR';

    /**
     * The HTTP status code for this exception.
     *
     * @since 1.1.0
     */
    protected int $statusCode = 500;

    /**
     * Create a new CMS Framework exception.
     *
     * @param  string  $message  The exception message.
     * @param  int  $code  The exception code (default: 0).
     * @param  Exception|null  $previous  The previous exception for chaining (default: null).
     *
     * @since 1.0.0
     */
    public function __construct( string $message = '', int $code = 0, ?Exception $previous = null )
    {
        parent::__construct( $message, $code, $previous );
    }

    /**
     * Get the machine-readable error code.
     *
     * @since 1.1.0
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get the HTTP status code.
     *
     * @since 1.1.0
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Render the exception as an HTTP response.
     *
     * Returns a consistent JSON error format for all CMS Framework exceptions
     * when the request expects JSON.
     *
     * @since 1.1.0
     *
     * @param  Request  $request  The incoming HTTP request.
     *
     * @return JsonResponse|null The JSON response or null to fall back to default rendering.
     */
    public function render( Request $request ): ?JsonResponse
    {
        if ( ! $request->expectsJson() ) {
            return null;
        }

        return new JsonResponse(
            $this->buildErrorPayload(),
            $this->statusCode,
        );
    }

    /**
     * Build the error response payload.
     *
     * Subclasses can override this method to add additional fields
     * (e.g., validation errors) to the response.
     *
     * @since 1.1.0
     *
     * @return array{error: array{code: string, message: string}}
     */
    protected function buildErrorPayload(): array
    {
        return [
            'error' => [
                'code'    => $this->errorCode,
                'message' => $this->getMessage(),
            ],
        ];
    }
}
