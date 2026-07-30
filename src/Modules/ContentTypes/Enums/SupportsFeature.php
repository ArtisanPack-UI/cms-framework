<?php

declare( strict_types=1 );

/**
 * Supports Feature Enum
 *
 * The canonical set of `supports` flags a content type may declare, mirroring
 * WordPress's `post_type_supports()` pattern. Post, Page, and admin-created
 * ContentType records all resolve their per-type supports arrays through this
 * enum so the vocabulary can never drift between them.
 *
 * @since 2.6.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums;

/**
 * Enum for content-type supports flags.
 *
 * `Title` is treated as always-on by {@see \ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasSupports::supportsFeature()}
 * and appears in this list for completeness of the admin UI's toggle set
 * rather than as an opt-in flag.
 *
 * @since 2.6.0
 */
enum SupportsFeature: string
{
    /**
     * List every flag as its string value.
     *
     * @since 2.6.0
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map( fn ( self $case ): string => $case->value, self::cases() );
    }

    /**
     * Filter an arbitrary array down to known flag values, preserving order.
     * Silently drops unknown entries so a stale `content_types.supports` row
     * carrying a removed flag can't leak into a validated payload.
     *
     * @since 2.6.0
     *
     * @param  array<int,mixed>  $values
     *
     * @return list<string>
     */
    public static function filter( array $values ): array
    {
        $known = self::values();

        return array_values( array_filter(
            array_map( fn ( $value ): string => is_string( $value ) ? $value : '', $values ),
            fn ( string $value ): bool => '' !== $value && in_array( $value, $known, true ),
        ) );
    }
    case Title          = 'title';
    case Editor         = 'editor';
    case Excerpt        = 'excerpt';
    case FeaturedImage  = 'featured_image';
    case Categories     = 'categories';
    case Tags           = 'tags';
    case CustomFields   = 'custom_fields';
    case Seo            = 'seo';
    case Author         = 'author';
    case PageAttributes = 'page_attributes';
    case Revisions      = 'revisions';
    case Templates      = 'templates';
}
