<?php

declare(strict_types=1);

/**
 * User Exception
 *
 * Exception class for user-related errors in the CMS framework.
 * Handles user authentication, registration, profile management, and
 * other user operations with specific error codes and context data.
 *
 * @since 1.0.0
 * @author Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Exceptions;

use Throwable;

/**
 * User Exception Class
 *
 * Specialized exception for user operations.
 */
class UserException extends CMSException
{
    // User-specific error codes
    public const USER_NOT_FOUND = 4001;
    public const USER_CREATION_FAILED = 4002;
    public const USER_UPDATE_FAILED = 4003;
    public const USER_DELETION_FAILED = 4004;
    public const USER_AUTHENTICATION_FAILED = 4005;
    public const USER_ALREADY_EXISTS = 4006;
    public const USER_INVALID_CREDENTIALS = 4007;
    public const USER_ACCOUNT_DISABLED = 4008;
    public const USER_ACCOUNT_LOCKED = 4009;
    public const USER_EMAIL_NOT_VERIFIED = 4010;
    public const USER_PASSWORD_WEAK = 4011;
    public const USER_PASSWORD_EXPIRED = 4012;
    public const USER_PROFILE_UPDATE_FAILED = 4013;
    public const USER_ROLE_ASSIGNMENT_FAILED = 4014;
    public const USER_PERMISSION_DENIED = 4015;

    /**
     * Error category for users.
     */
    protected string $category = 'user';

    /**
     * Create a user not found exception.
     */
    public static function userNotFound(int $userId, ?string $userMessage = null): static
    {
        return new static(
            message: "User with ID '{$userId}' not found",
            code: self::USER_NOT_FOUND,
            context: ['user_id' => $userId],
            userMessage: $userMessage ?? "The requested user could not be found."
        );
    }

    /**
     * Create a user not found by email exception.
     */
    public static function userNotFoundByEmail(string $email, ?string $userMessage = null): static
    {
        return new static(
            message: "User with email '{$email}' not found",
            code: self::USER_NOT_FOUND,
            context: ['email' => $email],
            userMessage: $userMessage ?? "No account found with this email address."
        );
    }

    /**
     * Create a user not found by username exception.
     */
    public static function userNotFoundByUsername(string $username, ?string $userMessage = null): static
    {
        return new static(
            message: "User with username '{$username}' not found",
            code: self::USER_NOT_FOUND,
            context: ['username' => $username],
            userMessage: $userMessage ?? "No account found with this username."
        );
    }

    /**
     * Create a creation failed exception.
     */
    public static function creationFailed(array $data, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        // Remove sensitive data from context
        $sanitizedData = $data;
        unset($sanitizedData['password'], $sanitizedData['password_confirmation']);
        
        return new static(
            message: "User creation failed: {$reason}",
            code: self::USER_CREATION_FAILED,
            previous: $previous,
            context: ['data' => $sanitizedData, 'reason' => $reason],
            userMessage: $userMessage ?? "Failed to create user account. Please try again."
        );
    }

    /**
     * Create an update failed exception.
     */
    public static function updateFailed(int $userId, array $data, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        // Remove sensitive data from context
        $sanitizedData = $data;
        unset($sanitizedData['password'], $sanitizedData['password_confirmation']);
        
        return new static(
            message: "User update failed for ID '{$userId}': {$reason}",
            code: self::USER_UPDATE_FAILED,
            previous: $previous,
            context: ['user_id' => $userId, 'data' => $sanitizedData, 'reason' => $reason],
            userMessage: $userMessage ?? "Failed to update user profile. Please try again."
        );
    }

    /**
     * Create a deletion failed exception.
     */
    public static function deletionFailed(int $userId, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "User deletion failed for ID '{$userId}': {$reason}",
            code: self::USER_DELETION_FAILED,
            previous: $previous,
            context: ['user_id' => $userId, 'reason' => $reason],
            userMessage: $userMessage ?? "Failed to delete user account. Please try again."
        );
    }

    /**
     * Create an authentication failed exception.
     */
    public static function authenticationFailed(string $identifier, string $reason, ?string $userMessage = null): static
    {
        return new static(
            message: "Authentication failed for '{$identifier}': {$reason}",
            code: self::USER_AUTHENTICATION_FAILED,
            context: ['identifier' => $identifier, 'reason' => $reason],
            userMessage: $userMessage ?? "Authentication failed. Please check your credentials."
        )->setSeverity('warning');
    }

    /**
     * Create an already exists exception.
     */
    public static function alreadyExists(string $field, string $value, ?string $userMessage = null): static
    {
        return new static(
            message: "User with {$field} '{$value}' already exists",
            code: self::USER_ALREADY_EXISTS,
            context: ['field' => $field, 'value' => $value],
            userMessage: $userMessage ?? "An account with this {$field} already exists."
        );
    }

    /**
     * Create an invalid credentials exception.
     */
    public static function invalidCredentials(string $identifier, ?string $userMessage = null): static
    {
        return new static(
            message: "Invalid credentials provided for '{$identifier}'",
            code: self::USER_INVALID_CREDENTIALS,
            context: ['identifier' => $identifier],
            userMessage: $userMessage ?? "Invalid email or password. Please try again."
        )->setSeverity('warning');
    }

    /**
     * Create an account disabled exception.
     */
    public static function accountDisabled(int $userId, string $reason, ?string $userMessage = null): static
    {
        return new static(
            message: "User account '{$userId}' is disabled: {$reason}",
            code: self::USER_ACCOUNT_DISABLED,
            context: ['user_id' => $userId, 'reason' => $reason],
            userMessage: $userMessage ?? "Your account has been disabled. Please contact support."
        );
    }

    /**
     * Create an account locked exception.
     */
    public static function accountLocked(int $userId, int $attempts, string $lockDuration, ?string $userMessage = null): static
    {
        return new static(
            message: "User account '{$userId}' locked after {$attempts} failed attempts for {$lockDuration}",
            code: self::USER_ACCOUNT_LOCKED,
            context: ['user_id' => $userId, 'failed_attempts' => $attempts, 'lock_duration' => $lockDuration],
            userMessage: $userMessage ?? "Your account has been temporarily locked due to too many failed login attempts."
        );
    }

    /**
     * Create an email not verified exception.
     */
    public static function emailNotVerified(int $userId, string $email, ?string $userMessage = null): static
    {
        return new static(
            message: "User '{$userId}' email '{$email}' not verified",
            code: self::USER_EMAIL_NOT_VERIFIED,
            context: ['user_id' => $userId, 'email' => $email],
            userMessage: $userMessage ?? "Please verify your email address before continuing."
        );
    }

    /**
     * Create a weak password exception.
     */
    public static function passwordWeak(array $requirements, ?string $userMessage = null): static
    {
        return new static(
            message: "Password does not meet strength requirements: " . implode(', ', $requirements),
            code: self::USER_PASSWORD_WEAK,
            context: ['requirements' => $requirements],
            userMessage: $userMessage ?? "Password is too weak. Please choose a stronger password."
        );
    }

    /**
     * Create a password expired exception.
     */
    public static function passwordExpired(int $userId, string $expiryDate, ?string $userMessage = null): static
    {
        return new static(
            message: "Password for user '{$userId}' expired on {$expiryDate}",
            code: self::USER_PASSWORD_EXPIRED,
            context: ['user_id' => $userId, 'expiry_date' => $expiryDate],
            userMessage: $userMessage ?? "Your password has expired. Please change your password to continue."
        );
    }

    /**
     * Create a profile update failed exception.
     */
    public static function profileUpdateFailed(int $userId, array $fields, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Profile update failed for user '{$userId}' on fields [" . implode(', ', $fields) . "]: {$reason}",
            code: self::USER_PROFILE_UPDATE_FAILED,
            previous: $previous,
            context: ['user_id' => $userId, 'fields' => $fields, 'reason' => $reason],
            userMessage: $userMessage ?? "Failed to update profile. Please try again."
        );
    }

    /**
     * Create a role assignment failed exception.
     */
    public static function roleAssignmentFailed(int $userId, int $roleId, string $reason, ?Throwable $previous = null, ?string $userMessage = null): static
    {
        return new static(
            message: "Role assignment failed for user '{$userId}' to role '{$roleId}': {$reason}",
            code: self::USER_ROLE_ASSIGNMENT_FAILED,
            previous: $previous,
            context: ['user_id' => $userId, 'role_id' => $roleId, 'reason' => $reason],
            userMessage: $userMessage ?? "Failed to assign user role. Please try again."
        );
    }

    /**
     * Create a permission denied exception.
     */
    public static function permissionDenied(int $userId, string $operation, ?string $resource = null, ?string $userMessage = null): static
    {
        $message = "User '{$userId}' denied permission to '{$operation}'";
        $context = ['user_id' => $userId, 'operation' => $operation];
        
        if ($resource) {
            $message .= " on resource '{$resource}'";
            $context['resource'] = $resource;
        }
        
        return new static(
            message: $message,
            code: self::USER_PERMISSION_DENIED,
            context: $context,
            userMessage: $userMessage ?? "You don't have permission to perform this action."
        );
    }
}