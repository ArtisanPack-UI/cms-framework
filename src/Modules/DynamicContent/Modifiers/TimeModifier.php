<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Contracts\Modifier;
use Illuminate\Support\Carbon;
use Throwable;

class TimeModifier implements Modifier
{
    public function slug(): string
    {
        return 'time';
    }

    public function apply( mixed $value, array $args ): mixed
    {
        if ( null === $value || '' === $value ) {
            return '';
        }

        // Args arrive already split on ":" by the tokenizer, so rejoin them to
        // reconstruct multi-part format strings like "g:i a".
        $format = empty( $args ) ? 'H:i' : implode( ':', $args );

        try {
            return Carbon::parse( $value )->format( $format );
        } catch ( Throwable ) {
            return $value;
        }
    }
}
