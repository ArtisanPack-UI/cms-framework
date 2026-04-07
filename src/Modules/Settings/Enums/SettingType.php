<?php

declare( strict_types=1 );

/**
 * Setting Type Enum
 *
 * Defines the available data types for stored settings and centralizes
 * type detection, casting, and serialization logic.
 *
 * @since 1.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Settings\Enums;

use JsonException;

/**
 * Enum for setting data types.
 *
 * @since 1.1.0
 */
enum SettingType: string
{
    /**
     * Detect the appropriate SettingType from a PHP value.
     *
     * @since 1.1.0
     *
     * @param  mixed  $value  The PHP value to detect the type of.
     *
     * @return self The detected setting type.
     */
    public static function fromValue( mixed $value ): self
    {
        return match ( true ) {
            is_bool( $value )                        => self::Boolean,
            is_int( $value )                         => self::Integer,
            is_float( $value )                       => self::Float,
            is_array( $value ), is_object( $value )    => self::Json,
            default                                => self::String,
        };
    }

    /**
     * Cast a raw stored value back to its native PHP type.
     *
     * @since 1.1.0
     *
     * @param  mixed  $value  The raw value from the database.
     *
     * @throws JsonException If the value is malformed JSON when type is Json.
     *
     * @return mixed The value cast to the appropriate PHP type.
     */
    public function cast( mixed $value ): mixed
    {
        if ( is_null( $value ) ) {
            return ( self::String === $this ) ? '' : null;
        }

        return match ( $this ) {
            self::Boolean => filter_var( $value, FILTER_VALIDATE_BOOLEAN ),
            self::Integer => (int) $value,
            self::Float   => (float) $value,
            self::Json    => json_decode( $value, true, 512, JSON_THROW_ON_ERROR ),
            self::String  => (string) $value,
        };
    }

    /**
     * Serialize a PHP value for database storage.
     *
     * @since 1.1.0
     *
     * @param  mixed  $value  The PHP value to serialize.
     *
     * @throws JsonException If the value cannot be encoded when type is Json.
     *
     * @return string The serialized string for storage.
     */
    public function serialize( mixed $value ): string
    {
        return match ( $this ) {
            self::Boolean              => $value ? '1' : '0',
            self::Integer, self::Float => (string) $value,
            self::Json                 => json_encode( $value, JSON_THROW_ON_ERROR ),
            self::String               => is_null( $value ) ? '' : (string) $value,
        };
    }
    case String  = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Float   = 'float';
    case Json    = 'json';
}
