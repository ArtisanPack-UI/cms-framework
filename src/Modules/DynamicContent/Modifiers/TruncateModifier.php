<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Contracts\Modifier;

class TruncateModifier implements Modifier
{
    public function slug(): string
    {
        return 'truncate';
    }

    public function apply( mixed $value, array $args ): mixed
    {
        if ( ! is_string( $value ) ) {
            return $value;
        }

        $length = isset( $args[0] ) ? (int) $args[0] : 100;

        if ( mb_strlen( $value ) <= $length ) {
            return $value;
        }

        return mb_substr( $value, 0, $length ) . '…';
    }
}
