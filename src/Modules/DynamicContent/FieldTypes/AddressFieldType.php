<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\FieldTypes;

use Illuminate\Support\HtmlString;

/**
 * Compound address field.
 *
 * Value shape:
 *   [ 'line1' => ..., 'line2' => ..., 'city' => ..., 'region' => ..., 'postal_code' => ..., 'country' => ... ]
 *
 * @since 2.4.0
 */
class AddressFieldType extends AbstractFieldType
{
    public function slug(): string
    {
        return 'address';
    }

    public function label(): string
    {
        return __( 'Address' );
    }

    public function render( mixed $value, array $options = [] ): HtmlString
    {
        if ( ! is_array( $value ) ) {
            return new HtmlString( '' );
        }

        $parts = array_filter( [
            $value['line1'] ?? null,
            $value['line2'] ?? null,
            trim( ( $value['city'] ?? '' ) . ', ' . ( $value['region'] ?? '' ) . ' ' . ( $value['postal_code'] ?? '' ) ),
            $value['country'] ?? null,
        ], fn ( $part ) => null !== $part && '' !== trim( (string) $part, ', ' ) );

        return new HtmlString( e( implode( ', ', $parts ) ) );
    }
}
