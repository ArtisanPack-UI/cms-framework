<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Contracts\Modifier;

class DefaultModifier implements Modifier
{
    public function slug(): string
    {
        return 'default';
    }

    public function apply( mixed $value, array $args ): mixed
    {
        if ( null === $value || '' === $value ) {
            return $args[0] ?? '';
        }

        return $value;
    }
}
