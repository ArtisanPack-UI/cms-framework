<?php

declare(strict_types=1);

/**
 * Authorization Exception
 *
 * Exception class for authorization and permission-related errors in the CMS framework.
 * Handles access control, role-based permissions, and security authorization failures
 * with specific error codes and context data.
 *
 * @since 1.0.0
 * @author Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Exceptions;

use Throwable;

/**
 * Authorization Exception Class
 *
 * Specialized exception for authorization and permission operations.
 */
class AuthorizationException extends CMSException
{
    // Authorization-specific error codes
    public const ACCESS_DENIED = 5001;
    public const INSUFFICIENT_PERMISSIONS = 5002;
    public const ROLE_NOT_FOUND = 5003;
    public const CAPABILITY_NOT_FOUND = 5004;
    public const UNAUTHORIZED_ACTION = 5005;
    public const FORBIDDEN_RESOURCE = 5006;
    public const TOKEN_EXPIRED = 5007;
    public const TOKEN_INVALID = 5008;
    public const SESSION_EXPIRED = 5009;
    public const IP_ADDRESS_BLOCKED = 5010;
    public const RATE_LIMIT_EXCEEDED = 5011;
    public const TWO_FACTOR_REQUIRED = 5012;
    public const SECURITY_VIOLATION = 5013;
    public const PRIVILEGE_ESCALATION = 5014;
    public const CONCURRENT_SESSION_LIMIT = 5015;

    /**
     * Error category for authorization.
     */
    protected string $category = 'authorization';

    /**
     * Create an access denied exception.
     */
    public static function accessDenied(int $userId, string $resource, string $action, ?string $userMessage = null): static
    {
        return new static(
            message: "Access denied for user '{$userId}' to '{$action}' on '{$resource}'",
            code: self::ACCESS_DENIED,
            context: [
                'user_id' => $userId,
                'resource' => $resource,
                'action' => $action
            ],
            userMessage: $userMessage ?? "You don't have permission to access this resource."
        );
    }

    /**
     * Create an insufficient permissions exception.
     */
    public static function insufficientPermissions(int $userId, array $requiredPermissions, array $userPermissions, ?string $userMessage = null): static
    {
        $missingPermissions = array_diff($requiredPermissions, $userPermissions);
        
        return new static(
            message: "User '{$userId}' has insufficient permissions. Required: [" . implode(', ', $requiredPermissions) . "], Missing: [" . implode(', ', $missingPermissions) . "]",
            code: self::INSUFFICIENT_PERMISSIONS,
            context: [
                'user_id' => $userId,
                'required_permissions' => $requiredPermissions,
                'user_permissions' => $userPermissions,
                'missing_permissions' => $missingPermissions
            ],
            userMessage: $userMessage ?? "You don't have sufficient permissions to perform this action."
        );
    }

    /**
     * Create a role not found exception.
     */
    public static function roleNotFound(string $roleIdentifier, ?string $userMessage = null): static
    {
        return new static(
            message: "Role '{$roleIdentifier}' not found",
            code: self::ROLE_NOT_FOUND,
            context: ['role_identifier' => $roleIdentifier],
            userMessage: $userMessage ?? "The specified role could not be found."
        );
    }

    /**
     * Create a capability not found exception.
     */
    public static function capabilityNotFound(string $capability, ?string $userMessage = null): static
    {
        return new static(
            message: "Capability '{$capability}' not found or invalid",
            code: self::CAPABILITY_NOT_FOUND,
            context: ['capability' => $capability],
            userMessage: $userMessage ?? "The requested capability is not valid."
        );
    }

    /**
     * Create an unauthorized action exception.
     */
    public static function unauthorizedAction(int $userId, string $action, array $context = [], ?string $userMessage = null): static
    {
        return new static(
            message: "Unauthorized action '{$action}' attempted by user '{$userId}'",
            code: self::UNAUTHORIZED_ACTION,
            context: array_merge(['user_id' => $userId, 'action' => $action], $context),
            userMessage: $userMessage ?? "You are not authorized to perform this action."
        );
    }

    /**
     * Create a forbidden resource exception.
     */
    public static function forbiddenResource(int $userId, string $resourceType, int $resourceId, ?string $userMessage = null): static
    {
        return new static(
            message: "User '{$userId}' forbidden access to {$resourceType} '{$resourceId}'",
            code: self::FORBIDDEN_RESOURCE,
            context: [
                'user_id' => $userId,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId
            ],
            userMessage: $userMessage ?? "Access to this resource is forbidden."
        );
    }

    /**
     * Create a token expired exception.
     */
    public static function tokenExpired(string $tokenType, string $expiryTime, ?string $userMessage = null): static
    {
        return new static(
            message: "{$tokenType} token expired at {$expiryTime}",
            code: self::TOKEN_EXPIRED,
            context: ['token_type' => $tokenType, 'expiry_time' => $expiryTime],
            userMessage: $userMessage ?? "Your session has expired. Please log in again."
        );
    }

    /**
     * Create a token invalid exception.
     */
    public static function tokenInvalid(string $tokenType, string $reason, ?string $userMessage = null): static
    {
        return new static(
            message: "{$tokenType} token is invalid: {$reason}",
            code: self::TOKEN_INVALID,
            context: ['token_type' => $tokenType, 'reason' => $reason],
            userMessage: $userMessage ?? "Invalid authentication token. Please log in again."
        );
    }

    /**
     * Create a session expired exception.
     */
    public static function sessionExpired(string $sessionId, string $expiryTime, ?string $userMessage = null): static
    {
        return new static(
            message: "Session '{$sessionId}' expired at {$expiryTime}",
            code: self::SESSION_EXPIRED,
            context: ['session_id' => $sessionId, 'expiry_time' => $expiryTime],
            userMessage: $userMessage ?? "Your session has expired. Please log in again."
        );
    }

    /**
     * Create an IP address blocked exception.
     */
    public static function ipAddressBlocked(string $ipAddress, string $reason, ?string $userMessage = null): static
    {
        return new static(
            message: "IP address '{$ipAddress}' is blocked: {$reason}",
            code: self::IP_ADDRESS_BLOCKED,
            context: ['ip_address' => $ipAddress, 'reason' => $reason],
            userMessage: $userMessage ?? "Access from your IP address has been blocked."
        )->setSeverity('critical');
    }

    /**
     * Create a rate limit exceeded exception.
     */
    public static function rateLimitExceeded(int $userId, string $action, int $limit, string $timeWindow, ?string $userMessage = null): static
    {
        return new static(
            message: "Rate limit exceeded for user '{$userId}' on action '{$action}': {$limit} requests per {$timeWindow}",
            code: self::RATE_LIMIT_EXCEEDED,
            context: [
                'user_id' => $userId,
                'action' => $action,
                'limit' => $limit,
                'time_window' => $timeWindow
            ],
            userMessage: $userMessage ?? "Too many requests. Please try again later."
        )->setSeverity('warning');
    }

    /**
     * Create a two-factor required exception.
     */
    public static function twoFactorRequired(int $userId, string $method, ?string $userMessage = null): static
    {
        return new static(
            message: "Two-factor authentication required for user '{$userId}' using method '{$method}'",
            code: self::TWO_FACTOR_REQUIRED,
            context: ['user_id' => $userId, 'method' => $method],
            userMessage: $userMessage ?? "Two-factor authentication is required to complete this action."
        );
    }

    /**
     * Create a security violation exception.
     */
    public static function securityViolation(int $userId, string $violationType, array $details, ?string $userMessage = null): static
    {
        return new static(
            message: "Security violation detected for user '{$userId}': {$violationType}",
            code: self::SECURITY_VIOLATION,
            context: array_merge(['user_id' => $userId, 'violation_type' => $violationType], $details),
            userMessage: $userMessage ?? "A security violation has been detected. This incident has been logged."
        )->setSeverity('critical')->setReportable(true);
    }

    /**
     * Create a privilege escalation exception.
     */
    public static function privilegeEscalation(int $userId, int $targetUserId, string $attemptedAction, ?string $userMessage = null): static
    {
        return new static(
            message: "Privilege escalation attempt by user '{$userId}' targeting user '{$targetUserId}' with action '{$attemptedAction}'",
            code: self::PRIVILEGE_ESCALATION,
            context: [
                'user_id' => $userId,
                'target_user_id' => $targetUserId,
                'attempted_action' => $attemptedAction
            ],
            userMessage: $userMessage ?? "Unauthorized privilege escalation attempt detected."
        )->setSeverity('critical')->setReportable(true);
    }

    /**
     * Create a concurrent session limit exception.
     */
    public static function concurrentSessionLimit(int $userId, int $maxSessions, int $currentSessions, ?string $userMessage = null): static
    {
        return new static(
            message: "Concurrent session limit exceeded for user '{$userId}': {$currentSessions}/{$maxSessions} sessions",
            code: self::CONCURRENT_SESSION_LIMIT,
            context: [
                'user_id' => $userId,
                'max_sessions' => $maxSessions,
                'current_sessions' => $currentSessions
            ],
            userMessage: $userMessage ?? "Maximum number of concurrent sessions exceeded. Please close other sessions."
        );
    }
}