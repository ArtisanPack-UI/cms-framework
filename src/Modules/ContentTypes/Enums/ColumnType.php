<?php

declare(strict_types=1);

/**
 * Column Type Enum
 *
 * Defines the available database column types for custom fields.
 *
 * @since 1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Enum for custom field database column types.
 *
 * @since 1.1.0
 */
enum ColumnType: string
{
    /**
     * Get the label for the column type.
     *
     * @since 1.1.0
     *
     * @return string The human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::String     => __('String'),
            self::Text       => __('Text'),
            self::Integer    => __('Integer'),
            self::BigInteger => __('Big Integer'),
            self::Decimal    => __('Decimal'),
            self::Float      => __('Float'),
            self::Double     => __('Double'),
            self::Boolean    => __('Boolean'),
            self::Date       => __('Date'),
            self::DateTime   => __('DateTime'),
            self::Time       => __('Time'),
            self::Json       => __('JSON'),
            self::Binary     => __('Binary'),
        };
    }

    /**
     * Get the validation rule for column type fields.
     *
     * @since 1.1.0
     *
     * @return Enum The validation rule.
     */
    public static function validationRule(): Enum
    {
        return Rule::enum(self::class);
    }
    case String     = 'string';
    case Text       = 'text';
    case Integer    = 'integer';
    case BigInteger = 'bigInteger';
    case Decimal    = 'decimal';
    case Float      = 'float';
    case Double     = 'double';
    case Boolean    = 'boolean';
    case Date       = 'date';
    case DateTime   = 'dateTime';
    case Time       = 'time';
    case Json       = 'json';
    case Binary     = 'binary';
}
