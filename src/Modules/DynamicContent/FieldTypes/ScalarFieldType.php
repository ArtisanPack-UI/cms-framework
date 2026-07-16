<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\FieldTypes;

/**
 * Data-driven field type for plain scalar values.
 *
 * Text, email, phone, URL, date, datetime, and select all share the same
 * "cast raw, render escaped" behavior — this class holds them so we don't
 * ship seven byte-identical shells.
 *
 * @since 2.4.0
 */
class ScalarFieldType extends AbstractFieldType
{
    public function __construct(
        protected string $slug,
        protected string $translationKey,
    ) {
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function label(): string
    {
        return __( $this->translationKey );
    }
}
