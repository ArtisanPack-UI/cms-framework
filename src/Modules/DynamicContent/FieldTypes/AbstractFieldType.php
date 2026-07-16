<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\FieldTypes;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Contracts\FieldType;
use Illuminate\Support\HtmlString;

/**
 * Shared behavior for built-in text-shaped field types.
 *
 * Renders scalar values as HTML-escaped strings so the resolver's raw
 * output path can't leak stored XSS. Field types that emit intentional
 * HTML (rich text) or safe URLs override `render()`.
 *
 * @since 2.4.0
 */
abstract class AbstractFieldType implements FieldType
{
    public function cast( mixed $value, array $options = [] ): mixed
    {
        return $value;
    }

    public function render( mixed $value, array $options = [] ): HtmlString
    {
        if ( $value instanceof HtmlString ) {
            return $value;
        }

        if ( null === $value ) {
            return new HtmlString( '' );
        }

        if ( is_bool( $value ) ) {
            return new HtmlString( $value ? '1' : '0' );
        }

        if ( is_scalar( $value ) ) {
            return new HtmlString( e( (string) $value ) );
        }

        return new HtmlString( '' );
    }
}
