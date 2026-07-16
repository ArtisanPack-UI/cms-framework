<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast for {@see \ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentRecordValue}
 * that transparently round-trips scalars, arrays, and null through the JSON
 * column without a sentinel wrapper.
 *
 * Storage rules:
 *   - null                        → SQL NULL
 *   - scalar (string|int|float|bool) → its JSON-encoded literal (`"foo"`, `42`, `true`)
 *   - array                       → its JSON-encoded object/array
 *
 * @implements CastsAttributes<mixed, mixed>
 *
 * @since 2.4.0
 */
class RecordValueCast implements CastsAttributes
{
    public function get( Model $model, string $key, mixed $value, array $attributes ): mixed
    {
        if ( null === $value ) {
            return null;
        }

        if ( ! is_string( $value ) ) {
            return $value;
        }

        $decoded = json_decode( $value, true );

        return JSON_ERROR_NONE === json_last_error() ? $decoded : $value;
    }

    public function set( Model $model, string $key, mixed $value, array $attributes ): mixed
    {
        if ( null === $value ) {
            return null;
        }

        return json_encode( $value );
    }
}
