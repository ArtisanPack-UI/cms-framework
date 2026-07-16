<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Contracts\Modifier;
use Illuminate\Support\HtmlString;

/**
 * Converts newlines to `<br>` tags after HTML-escaping the input.
 *
 * Returns an {@see HtmlString} so downstream field-type rendering
 * doesn't double-escape the inserted `<br>` markup.
 *
 * @since 2.4.0
 */
class Nl2brModifier implements Modifier
{
    public function slug(): string
    {
        return 'nl2br';
    }

    public function apply( mixed $value, array $args ): mixed
    {
        if ( $value instanceof HtmlString ) {
            $value = (string) $value;
        }

        if ( ! is_string( $value ) ) {
            return $value;
        }

        return new HtmlString( nl2br( e( $value ), false ) );
    }
}
