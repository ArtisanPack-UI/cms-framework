<?php

declare( strict_types=1 );

/**
 * Field Type Enum
 *
 * Defines the available field types for custom fields.
 *
 * @since 1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Enum for custom field types.
 *
 * @since 1.1.0
 */
enum FieldType: string
{
    /**
     * Get the label for the field type.
     *
     * @since 1.1.0
     *
     * @return string The human-readable label.
     */
    public function label(): string
    {
        return match ( $this ) {
            self::Text     => __( 'Text' ),
            self::Textarea => __( 'Textarea' ),
            self::Number   => __( 'Number' ),
            self::Select   => __( 'Select' ),
            self::Checkbox => __( 'Checkbox' ),
            self::Radio    => __( 'Radio' ),
            self::Boolean  => __( 'Boolean' ),
            self::Date     => __( 'Date' ),
            self::Datetime => __( 'Datetime' ),
            self::Time     => __( 'Time' ),
            self::Email    => __( 'Email' ),
            self::Url      => __( 'URL' ),
            self::Tel      => __( 'Telephone' ),
            self::Color    => __( 'Color' ),
            self::File     => __( 'File' ),
            self::Image    => __( 'Image' ),
        };
    }

    /**
     * Get the validation rule for field type fields.
     *
     * @since 1.1.0
     *
     * @return Enum The validation rule.
     */
    public static function validationRule(): Enum
    {
        return Rule::enum( self::class );
    }
    case Text     = 'text';
    case Textarea = 'textarea';
    case Number   = 'number';
    case Select   = 'select';
    case Checkbox = 'checkbox';
    case Radio    = 'radio';
    case Boolean  = 'boolean';
    case Date     = 'date';
    case Datetime = 'datetime';
    case Time     = 'time';
    case Email    = 'email';
    case Url      = 'url';
    case Tel      = 'tel';
    case Color    = 'color';
    case File     = 'file';
    case Image    = 'image';
}
