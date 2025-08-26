<?php

declare(strict_types=1);

/**
 * Input Sanitizer Utility
 *
 * Provides comprehensive input sanitization methods for security-focused validation.
 * Includes HTML purification, XSS prevention, filename sanitization, and other
 * security measures to prevent injection attacks and ensure data integrity.
 *
 * @since   1.0.0
 *
 * @author  Jacob Martella Web Design <info@jacobmartella.com>
 */

namespace ArtisanPackUI\CMSFramework\Http\Utilities;

use function ArtisanPackUI\Security\kses;
use function ArtisanPackUI\Security\sanitizeArray;
use function ArtisanPackUI\Security\sanitizeEmail;
use function ArtisanPackUI\Security\sanitizeFilename;
use function ArtisanPackUI\Security\sanitizeFloat;
use function ArtisanPackUI\Security\sanitizeInt;
use function ArtisanPackUI\Security\sanitizeText;
use function ArtisanPackUI\Security\sanitizeUrl;

/**
 * Input Sanitizer Utility Class
 *
 * Centralized utility for sanitizing various types of user input to prevent
 * security vulnerabilities including XSS, HTML injection, and file upload attacks.
 */
class InputSanitizer
{
    /**
     * Sanitize HTML content allowing safe HTML tags.
     *
     * @param  string|null  $content  The HTML content to sanitize
     * @return string The sanitized HTML content
     */
    public static function sanitizeHtml(?string $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        // Use security package kses for HTML sanitization with comprehensive allowed tags
        $config = [
            'safe' => 1,
            'elements' => 'p,br,strong,em,u,ol,ul,li,a[href],blockquote,h1,h2,h3,h4,h5,h6,img[src|alt|width|height],table,tr,td,th,thead,tbody',
        ];

        return kses($content, $config);
    }

    /**
     * Sanitize HTML content with strict rules (minimal HTML allowed).
     *
     * @param  string|null  $content  The HTML content to sanitize
     * @return string The sanitized content with minimal HTML
     */
    public static function sanitizeHtmlStrict(?string $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        // Use security package kses for strict HTML sanitization with minimal allowed tags
        $config = [
            'safe' => 1,
            'elements' => 'p,br,strong,em,a[href]',
        ];

        return kses($content, $config);
    }

    /**
     * Sanitize plain text content (strip all HTML and prevent XSS).
     *
     * @param  string|null  $text  The text to sanitize
     * @param  int  $maxLength  Maximum allowed length (0 = no limit)
     * @return string The sanitized text
     */
    public static function sanitizeText(?string $text, int $maxLength = 0): string
    {
        // Use security package for basic text sanitization
        $sanitized = sanitizeText($text);

        // Apply length limit if specified
        if ($maxLength > 0 && strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }

        return $sanitized;
    }

    /**
     * Sanitize email address.
     *
     * @param  string|null  $email  The email to sanitize
     * @return string The sanitized email
     */
    public static function sanitizeEmail(?string $email): string
    {
        return sanitizeEmail($email);
    }

    /**
     * Sanitize URL.
     *
     * @param  string|null  $url  The URL to sanitize
     * @return string The sanitized URL
     */
    public static function sanitizeUrl(?string $url): string
    {
        return sanitizeUrl($url);
    }

    /**
     * Sanitize filename for safe file operations.
     *
     * @param  string|null  $filename  The filename to sanitize
     * @return string The sanitized filename
     */
    public static function sanitizeFilename(?string $filename): string
    {
        // Use security package for basic filename sanitization
        $basicSanitized = sanitizeFilename($filename);

        if ($basicSanitized === '') {
            return '';
        }

        // Get file extension from the sanitized filename
        $pathinfo = pathinfo($basicSanitized);
        $name = $pathinfo['filename'] ?? '';
        $extension = isset($pathinfo['extension']) ? '.'.$pathinfo['extension'] : '';

        // Ensure we have a valid filename
        if (empty($name)) {
            $name = 'file_'.time();
        }

        // Enhanced extension validation for CMS security
        $safeExtensions = [
            '.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg', '.bmp',
            '.mp4', '.avi', '.mov', '.wmv', '.webm', '.mkv',
            '.mp3', '.wav', '.ogg', '.m4a',
            '.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx',
            '.txt', '.csv', '.json', '.xml',
        ];

        $extension = strtolower($extension);
        if (! in_array($extension, $safeExtensions, true)) {
            $extension = '.txt'; // Default safe extension
        }

        return $name.$extension;
    }

    /**
     * Sanitize integer value.
     *
     * @param  mixed  $value  The value to sanitize
     * @param  int  $min  Minimum allowed value
     * @param  int  $max  Maximum allowed value
     * @return int The sanitized integer
     */
    public static function sanitizeInteger($value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
    {
        // Use security package for basic integer sanitization
        $int = sanitizeInt($value);

        // Apply min/max constraints
        return max($min, min($max, $int));
    }

    /**
     * Sanitize float value.
     *
     * @param  mixed  $value  The value to sanitize
     * @param  float  $min  Minimum allowed value
     * @param  float  $max  Maximum allowed value
     * @return float The sanitized float
     */
    public static function sanitizeFloat($value, float $min = PHP_FLOAT_MIN, float $max = PHP_FLOAT_MAX): float
    {
        // Use security package for basic float sanitization
        $float = sanitizeFloat($value, 2);  // Default to 2 decimal places

        // Apply min/max constraints
        return max($min, min($max, $float));
    }

    /**
     * Sanitize array recursively.
     *
     * @param  array|null  $array  The array to sanitize
     * @param  string  $type  The type of sanitization ('text', 'html', 'email', etc.)
     * @param  int  $maxDepth  Maximum recursion depth
     * @return array The sanitized array
     */
    public static function sanitizeArray(?array $array, string $type = 'text', int $maxDepth = 10): array
    {
        if ($array === null) {
            return [];
        }

        if ($maxDepth <= 0) {
            return []; // Prevent infinite recursion
        }

        // Use security package for basic array sanitization as foundation
        $basicSanitized = sanitizeArray($array);
        $sanitized = [];

        foreach ($basicSanitized as $key => $value) {
            // Sanitize the key
            $cleanKey = self::sanitizeText((string) $key, 100);

            if (is_array($value)) {
                $sanitized[$cleanKey] = self::sanitizeArray($value, $type, $maxDepth - 1);
            } elseif (is_string($value)) {
                // Apply type-specific sanitization for CMS needs
                switch ($type) {
                    case 'html':
                        $sanitized[$cleanKey] = self::sanitizeHtml($value);
                        break;
                    case 'email':
                        $sanitized[$cleanKey] = self::sanitizeEmail($value);
                        break;
                    case 'url':
                        $sanitized[$cleanKey] = self::sanitizeUrl($value);
                        break;
                    default:
                        $sanitized[$cleanKey] = self::sanitizeText($value);
                }
            } elseif (is_numeric($value)) {
                $sanitized[$cleanKey] = is_float($value) ?
                    self::sanitizeFloat($value) :
                    self::sanitizeInteger($value);
            } elseif (is_bool($value)) {
                $sanitized[$cleanKey] = (bool) $value;
            } else {
                // For other types, convert to string and sanitize as text
                $sanitized[$cleanKey] = self::sanitizeText((string) $value);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize JSON string and return as array.
     *
     * @param  string|null  $json  The JSON string to sanitize
     * @param  string  $arrayType  The type of sanitization for array values
     * @return array The sanitized array from JSON
     */
    public static function sanitizeJson(?string $json, string $arrayType = 'text'): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        return self::sanitizeArray($decoded, $arrayType);
    }

    /**
     * Validate and sanitize password.
     *
     * @param  string|null  $password  The password to validate
     * @param  int  $minLength  Minimum password length
     * @param  bool  $requireSpecialChars  Require special characters
     * @param  bool  $requireNumbers  Require numbers
     * @param  bool  $requireUppercase  Require uppercase letters
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public static function validatePassword(
        ?string $password,
        int $minLength = 8,
        bool $requireSpecialChars = true,
        bool $requireNumbers = true,
        bool $requireUppercase = true
    ): array {
        $errors = [];

        if ($password === null || $password === '') {
            return ['valid' => false, 'errors' => ['Password is required']];
        }

        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters long";
        }

        if ($requireNumbers && ! preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        if ($requireUppercase && ! preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if ($requireSpecialChars && ! preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        // Check for common weak passwords
        $weakPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123',
            'password123', 'admin', 'letmein', 'welcome', '12345678',
        ];

        if (in_array(strtolower($password), $weakPasswords, true)) {
            $errors[] = 'Password is too common and weak';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
