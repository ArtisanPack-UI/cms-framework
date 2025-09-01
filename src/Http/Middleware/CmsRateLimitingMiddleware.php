<?php

declare(strict_types=1);

/**
 * CMS Rate Limiting Middleware
 *
 * Implements comprehensive rate limiting for API endpoints with support for
 * different limits based on endpoint types, user-specific and IP-based limiting,
 * admin bypass functionality, and proper rate limit headers.
 *
 * @since   1.0.0
 *
 * @author  Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * CMS Rate Limiting Middleware
 *
 * Provides rate limiting functionality for API endpoints with configurable
 * limits, admin bypass, and proper HTTP headers.
 */
class CmsRateLimitingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request  The incoming request
     * @param  Closure  $next  The next middleware in the pipeline
     * @param  string  $limitType  The type of rate limit to apply (general, auth, admin, upload)
     */
    public function handle(Request $request, Closure $next, string $limitType = 'general'): SymfonyResponse
    {
        // Check if rate limiting is enabled
        if (! config('cms.rate_limiting.enabled', true)) {
            return $next($request);
        }

        // Check for admin bypass
        if ($this->shouldBypassRateLimit($request)) {
            return $next($request);
        }

        // Get rate limit configuration for the specified type
        $config = $this->getRateLimitConfig($limitType);
        $limit = $config['requests_per_minute'];
        $keyGenerator = $config['key_generator'];

        // Generate the rate limiting key
        $key = $this->generateRateLimitKey($request, $keyGenerator, $limitType);

        // Check rate limit using Laravel's built-in rate limiter
        $response = RateLimiter::attempt(
            $key,
            $limit,
            function () use ($request, $next) {
                return $next($request);
            },
            60 // 1 minute decay time
        );

        // If rate limit exceeded, return 429 response
        if ($response === false) {
            return $this->buildTooManyRequestsResponse($key, $limit);
        }

        // Add rate limit headers to successful response
        if (config('cms.rate_limiting.headers.enabled', true)) {
            $this->addRateLimitHeaders($response, $key, $limit);
        }

        return $response;
    }

    /**
     * Check if the current user should bypass rate limiting.
     *
     * @param  Request  $request  The incoming request
     * @return bool Whether to bypass rate limiting
     */
    protected function shouldBypassRateLimit(Request $request): bool
    {
        // Check if bypass is enabled
        if (! config('cms.rate_limiting.bypass.enabled', true)) {
            return false;
        }

        // Check if user is authenticated
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        // Check if user has admin capabilities
        $adminCapabilities = config('cms.rate_limiting.bypass.admin_capabilities', ['manage_options']);

        foreach ($adminCapabilities as $capability) {
            if ($user->can($capability)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get rate limit configuration for a specific type.
     *
     * @param  string  $limitType  The rate limit type
     * @return array The configuration array
     */
    protected function getRateLimitConfig(string $limitType): array
    {
        $defaultConfig = [
            'requests_per_minute' => 60,
            'key_generator' => 'user_ip',
        ];

        $config = config("cms.rate_limiting.{$limitType}", $defaultConfig);

        // Ensure we have the required keys
        return array_merge($defaultConfig, $config);
    }

    /**
     * Generate a unique key for rate limiting based on the key generator type.
     *
     * @param  Request  $request  The incoming request
     * @param  string  $keyGenerator  The key generation strategy
     * @param  string  $limitType  The rate limit type
     * @return string The generated key
     */
    protected function generateRateLimitKey(Request $request, string $keyGenerator, string $limitType): string
    {
        $baseKey = "cms_rate_limit:{$limitType}:";

        switch ($keyGenerator) {
            case 'user_id':
                $user = Auth::user();
                if ($user) {
                    return $baseKey."user:{$user->id}";
                }

                // Fall back to IP if user not authenticated
                return $baseKey."ip:{$request->ip()}";

            case 'user_ip':
                $user = Auth::user();
                $userId = $user ? $user->id : 'guest';

                return $baseKey."user_ip:{$userId}:{$request->ip()}";

            case 'ip_only':
            default:
                return $baseKey."ip:{$request->ip()}";
        }
    }

    /**
     * Build a "Too Many Requests" response.
     *
     * @param  string  $key  The rate limit key
     * @param  int  $limit  The rate limit
     */
    protected function buildTooManyRequestsResponse(string $key, int $limit): Response
    {
        $retryAfter = RateLimiter::availableIn($key);

        $response = response()->json([
            'error' => 'Too Many Requests',
            'message' => 'Rate limit exceeded. Please try again later.',
            'retry_after' => $retryAfter,
        ], 429);

        // Add rate limit headers
        if (config('cms.rate_limiting.headers.enabled', true)) {
            $headers = config('cms.rate_limiting.headers');
            $response->headers->set($headers['limit_header'], (string) $limit);
            $response->headers->set($headers['remaining_header'], '0');
            $response->headers->set($headers['retry_after_header'], (string) $retryAfter);
        }

        return $response;
    }

    /**
     * Add rate limit headers to the response.
     *
     * @param  SymfonyResponse  $response  The response
     * @param  string  $key  The rate limit key
     * @param  int  $limit  The rate limit
     */
    protected function addRateLimitHeaders(SymfonyResponse $response, string $key, int $limit): void
    {
        $headers = config('cms.rate_limiting.headers');
        $remaining = RateLimiter::remaining($key, $limit);

        $response->headers->set($headers['limit_header'], (string) $limit);
        $response->headers->set($headers['remaining_header'], (string) max(0, $remaining));

        // Add retry-after header if rate limit is approaching
        if ($remaining <= 0) {
            $retryAfter = RateLimiter::availableIn($key);
            $response->headers->set($headers['retry_after_header'], (string) $retryAfter);
        }
    }
}
