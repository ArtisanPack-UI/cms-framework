<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Contracts\Modifier;

class LowerModifier implements Modifier
{
    public function slug(): string
    {
        return 'lower';
    }

    public function apply( mixed $value, array $args ): mixed
    {
        return is_string( $value ) ? mb_strtolower( $value ) : $value;
    }
}
