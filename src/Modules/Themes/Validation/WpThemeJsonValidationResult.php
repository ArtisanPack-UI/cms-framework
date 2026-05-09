<?php

/**
 * WP theme.json Validation Result
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Validation;

/**
 * Outcome of a {@see WpThemeJsonValidator::validate()} call.
 *
 * Carries enough detail for the caller to log a warning naming the offending
 * key when validation fails.
 *
 * @since 1.2.0
 */
final class WpThemeJsonValidationResult
{
    /**
     * @since 1.2.0
     *
     * @param  bool  $valid  True when the manifest passes WP-schema + extension shape checks.
     * @param  string|null  $offendingKey  Dotted path of the first failing property, or null on success.
     * @param  string|null  $message  Human-readable error message, or null on success.
     */
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $offendingKey,
        public readonly ?string $message,
    ) {}

    /**
     * Build a passing result.
     *
     * @since 1.2.0
     */
    public static function success(): self
    {
        return new self(true, null, null);
    }

    /**
     * Build a failing result.
     *
     * @since 1.2.0
     *
     * @param  string  $offendingKey  Dotted path of the failing property (e.g. `settings.color`).
     * @param  string  $message  Description of why the property failed.
     */
    public static function failure(string $offendingKey, string $message): self
    {
        return new self(false, $offendingKey, $message);
    }
}
