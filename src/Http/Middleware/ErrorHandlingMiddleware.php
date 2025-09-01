<?php

declare(strict_types=1);

/**
 * Error Handling Middleware
 *
 * Middleware for consistent error handling and response formatting across
 * the CMS framework. Handles both API and web requests with appropriate
 * error responses and structured logging.
 *
 * @since 1.0.0
 * @author Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Http\Middleware;

use ArtisanPackUI\CMSFramework\Exceptions\AuthorizationException;
use ArtisanPackUI\CMSFramework\Exceptions\CMSException;
use ArtisanPackUI\CMSFramework\Exceptions\ContentException;
use ArtisanPackUI\CMSFramework\Exceptions\MediaException;
use ArtisanPackUI\CMSFramework\Exceptions\PluginException;
use ArtisanPackUI\CMSFramework\Exceptions\UserException;
use ArtisanPackUI\CMSFramework\Services\StructuredLoggerService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException as LaravelAuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Error Handling Middleware
 *
 * Provides consistent error handling and response formatting.
 */
class ErrorHandlingMiddleware
{
    /**
     * Structured logger service.
     */
    private StructuredLoggerService $logger;

    /**
     * HTTP status code mappings for exceptions.
     */
    private array $statusCodeMap = [
        // CMS Exceptions
        PluginException::PLUGIN_NOT_FOUND => 404,
        PluginException::PLUGIN_ALREADY_EXISTS => 409,
        PluginException::PLUGIN_ALREADY_ACTIVE => 409,
        PluginException::PLUGIN_ALREADY_INACTIVE => 409,
        PluginException::PLUGIN_INSTALLATION_FAILED => 500,
        PluginException::PLUGIN_ACTIVATION_FAILED => 500,
        PluginException::PLUGIN_DEACTIVATION_FAILED => 500,
        PluginException::PLUGIN_INVALID_STRUCTURE => 422,
        PluginException::PLUGIN_DEPENDENCY_ERROR => 424,
        
        MediaException::MEDIA_NOT_FOUND => 404,
        MediaException::MEDIA_UPLOAD_FAILED => 500,
        MediaException::MEDIA_INVALID_TYPE => 422,
        MediaException::MEDIA_SIZE_EXCEEDED => 413,
        MediaException::MEDIA_PERMISSION_DENIED => 403,
        MediaException::MEDIA_QUOTA_EXCEEDED => 429,
        MediaException::MEDIA_VIRUS_DETECTED => 403,
        
        ContentException::CONTENT_NOT_FOUND => 404,
        ContentException::CONTENT_SLUG_DUPLICATE => 409,
        ContentException::CONTENT_PERMISSION_DENIED => 403,
        ContentException::CONTENT_VALIDATION_FAILED => 422,
        ContentException::CONTENT_INVALID_STATUS => 422,
        
        UserException::USER_NOT_FOUND => 404,
        UserException::USER_ALREADY_EXISTS => 409,
        UserException::USER_INVALID_CREDENTIALS => 401,
        UserException::USER_ACCOUNT_DISABLED => 403,
        UserException::USER_ACCOUNT_LOCKED => 423,
        UserException::USER_EMAIL_NOT_VERIFIED => 403,
        UserException::USER_PASSWORD_WEAK => 422,
        UserException::USER_PERMISSION_DENIED => 403,
        
        AuthorizationException::ACCESS_DENIED => 403,
        AuthorizationException::INSUFFICIENT_PERMISSIONS => 403,
        AuthorizationException::UNAUTHORIZED_ACTION => 403,
        AuthorizationException::FORBIDDEN_RESOURCE => 403,
        AuthorizationException::TOKEN_EXPIRED => 401,
        AuthorizationException::TOKEN_INVALID => 401,
        AuthorizationException::SESSION_EXPIRED => 401,
        AuthorizationException::IP_ADDRESS_BLOCKED => 403,
        AuthorizationException::RATE_LIMIT_EXCEEDED => 429,
        AuthorizationException::TWO_FACTOR_REQUIRED => 403,
    ];

    /**
     * Create a new error handling middleware instance.
     */
    public function __construct(StructuredLoggerService $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (Throwable $exception) {
            return $this->handleException($request, $exception);
        }
    }

    /**
     * Handle the exception and return appropriate response.
     */
    private function handleException(Request $request, Throwable $exception): SymfonyResponse
    {
        // Log the exception with structured context
        $this->logException($exception, $request);

        // Handle different types of exceptions
        if ($exception instanceof ValidationException) {
            return $this->handleValidationException($request, $exception);
        }

        if ($exception instanceof AuthenticationException) {
            return $this->handleAuthenticationException($request, $exception);
        }

        if ($exception instanceof LaravelAuthorizationException) {
            return $this->handleLaravelAuthorizationException($request, $exception);
        }

        if ($exception instanceof ModelNotFoundException) {
            return $this->handleModelNotFoundException($request, $exception);
        }

        if ($exception instanceof NotFoundHttpException) {
            return $this->handleNotFoundHttpException($request, $exception);
        }

        if ($exception instanceof HttpException) {
            return $this->handleHttpException($request, $exception);
        }

        if ($exception instanceof CMSException) {
            return $this->handleCMSException($request, $exception);
        }

        // Handle generic exceptions
        return $this->handleGenericException($request, $exception);
    }

    /**
     * Log the exception with structured context.
     */
    private function logException(Throwable $exception, Request $request): void
    {
        $context = [
            'url' => $request->fullUrl(),
            'method' => $request->getMethod(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'input' => $this->sanitizeInput($request->all()),
        ];

        $this->logger->exception($exception, $context);
    }

    /**
     * Handle validation exceptions.
     */
    private function handleValidationException(Request $request, ValidationException $exception): SymfonyResponse
    {
        if ($this->expectsJson($request)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'validation_error',
                    'message' => 'The given data was invalid.',
                    'code' => 422,
                    'details' => $exception->errors(),
                ],
                'meta' => $this->getResponseMeta($request),
            ], 422);
        }

        return redirect()->back()
            ->withErrors($exception->errors())
            ->withInput($request->except(['password', 'password_confirmation']));
    }

    /**
     * Handle authentication exceptions.
     */
    private function handleAuthenticationException(Request $request, AuthenticationException $exception): SymfonyResponse
    {
        if ($this->expectsJson($request)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'authentication_error',
                    'message' => 'Unauthenticated.',
                    'code' => 401,
                ],
                'meta' => $this->getResponseMeta($request),
            ], 401);
        }

        return redirect()->guest(route('login'));
    }

    /**
     * Handle Laravel authorization exceptions.
     */
    private function handleLaravelAuthorizationException(Request $request, LaravelAuthorizationException $exception): SymfonyResponse
    {
        if ($this->expectsJson($request)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'authorization_error',
                    'message' => 'This action is unauthorized.',
                    'code' => 403,
                ],
                'meta' => $this->getResponseMeta($request),
            ], 403);
        }

        return response()->view('errors.403', [], 403);
    }

    /**
     * Handle model not found exceptions.
     */
    private function handleModelNotFoundException(Request $request, ModelNotFoundException $exception): SymfonyResponse
    {
        $modelClass = class_basename($exception->getModel());
        
        if ($this->expectsJson($request)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'not_found',
                    'message' => "The requested {$modelClass} could not be found.",
                    'code' => 404,
                ],
                'meta' => $this->getResponseMeta($request),
            ], 404);
        }

        return response()->view('errors.404', [], 404);
    }

    /**
     * Handle not found HTTP exceptions.
     */
    private function handleNotFoundHttpException(Request $request, NotFoundHttpException $exception): SymfonyResponse
    {
        if ($this->expectsJson($request)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'not_found',
                    'message' => 'The requested resource could not be found.',
                    'code' => 404,
                ],
                'meta' => $this->getResponseMeta($request),
            ], 404);
        }

        return response()->view('errors.404', [], 404);
    }

    /**
     * Handle HTTP exceptions.
     */
    private function handleHttpException(Request $request, HttpException $exception): SymfonyResponse
    {
        $statusCode = $exception->getStatusCode();
        $message = $exception->getMessage() ?: Response::$statusTexts[$statusCode] ?? 'An error occurred.';

        if ($this->expectsJson($request)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'http_error',
                    'message' => $message,
                    'code' => $statusCode,
                ],
                'meta' => $this->getResponseMeta($request),
            ], $statusCode);
        }

        $viewName = "errors.{$statusCode}";
        if (view()->exists($viewName)) {
            return response()->view($viewName, compact('exception'), $statusCode);
        }

        return response()->view('errors.generic', compact('exception'), $statusCode);
    }

    /**
     * Handle CMS exceptions.
     */
    private function handleCMSException(Request $request, CMSException $exception): SymfonyResponse
    {
        $statusCode = $this->getStatusCodeForException($exception);
        
        if ($this->expectsJson($request)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => $exception->getCategory() . '_error',
                    'message' => $exception->getUserMessage() ?? $exception->getMessage(),
                    'code' => $exception->getCode(),
                    'severity' => $exception->getSeverity(),
                    'context' => Config::get('app.debug') ? $exception->getContext() : [],
                ],
                'meta' => $this->getResponseMeta($request),
            ], $statusCode);
        }

        $viewData = [
            'exception' => $exception,
            'userMessage' => $exception->getUserMessage(),
            'statusCode' => $statusCode,
        ];

        $viewName = "errors.{$statusCode}";
        if (view()->exists($viewName)) {
            return response()->view($viewName, $viewData, $statusCode);
        }

        return response()->view('errors.generic', $viewData, $statusCode);
    }

    /**
     * Handle generic exceptions.
     */
    private function handleGenericException(Request $request, Throwable $exception): SymfonyResponse
    {
        $statusCode = 500;
        $message = Config::get('app.debug') ? $exception->getMessage() : 'Internal Server Error';

        if ($this->expectsJson($request)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'type' => 'server_error',
                    'message' => $message,
                    'code' => $statusCode,
                    'debug' => Config::get('app.debug') ? [
                        'exception' => get_class($exception),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'trace' => $exception->getTraceAsString(),
                    ] : null,
                ],
                'meta' => $this->getResponseMeta($request),
            ], $statusCode);
        }

        return response()->view('errors.500', compact('exception'), 500);
    }

    /**
     * Get HTTP status code for CMS exception.
     */
    private function getStatusCodeForException(CMSException $exception): int
    {
        return $this->statusCodeMap[$exception->getCode()] ?? 500;
    }

    /**
     * Check if request expects JSON response.
     */
    private function expectsJson(Request $request): bool
    {
        return $request->expectsJson() || 
               $request->is('api/*') || 
               $request->ajax() ||
               $request->wantsJson();
    }

    /**
     * Get response metadata.
     */
    private function getResponseMeta(Request $request): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'request_id' => $request->header('X-Request-ID') ?? uniqid('req_'),
            'path' => $request->path(),
            'method' => $request->getMethod(),
        ];
    }

    /**
     * Sanitize request input for logging.
     */
    private function sanitizeInput(array $input): array
    {
        $sensitiveKeys = ['password', 'password_confirmation', 'token', 'secret', 'key', 'api_key'];
        
        foreach ($sensitiveKeys as $key) {
            if (isset($input[$key])) {
                $input[$key] = '[REDACTED]';
            }
        }

        return $input;
    }
}