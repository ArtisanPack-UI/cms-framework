<?php

declare(strict_types=1);

/**
 * Content Status Enum
 *
 * Defines the available content statuses for blog posts and pages.
 *
 * @since 1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Enum for content statuses.
 *
 * @since 1.1.0
 */
enum ContentStatus: string
{
    /**
     * Get the label for the content status.
     *
     * @since 1.1.0
     *
     * @return string The human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft     => __('Draft'),
            self::Published => __('Published'),
            self::Scheduled => __('Scheduled'),
            self::Private   => __('Private'),
        };
    }

    /**
     * Get the validation rule for content status fields.
     *
     * @since 1.1.0
     *
     * @return Enum The validation rule.
     */
    public static function validationRule(): Enum
    {
        return Rule::enum(self::class);
    }
    case Draft     = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Private   = 'private';
}
