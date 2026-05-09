<?php

/**
 * Slug Validator
 *
 * Single source of truth for the canonical kebab-case slug pattern used by
 * SiteEditor entities. Mirrors the regex enforced in the Form Requests so any
 * slug that could not have been written through the API is also rejected on
 * read paths and at controller boundaries that bypass the Form Request (e.g.
 * the route slug on PUT/DELETE).
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support;

/**
 * @since 1.2.0
 */
final class SlugValidator
{
    /**
     * Canonical kebab-case slug regex: lowercase a-z, digits, and single
     * hyphens between groups. Rejects path-traversal segments (`..`),
     * separators (`/`, `\`), null bytes, uppercase, and any other character.
     *
     * @since 1.2.0
     */
    public const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @since 1.2.0
     */
    public static function isValid(string $slug): bool
    {
        return (bool) preg_match(self::PATTERN, $slug);
    }

    /**
     * Pattern fragment for use in Laravel validation rule strings
     * (`'regex:' . SlugValidator::patternRule()`).
     *
     * @since 1.2.0
     */
    public static function patternRule(): string
    {
        return self::PATTERN;
    }
}
