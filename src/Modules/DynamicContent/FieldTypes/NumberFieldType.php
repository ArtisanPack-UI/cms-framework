<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\FieldTypes;

class NumberFieldType extends AbstractFieldType
{
    public function slug(): string
    {
        return 'number';
    }

    public function label(): string
    {
        return __( 'Number' );
    }

    public function cast( mixed $value, array $options = [] ): mixed
    {
        if ( null === $value || '' === $value ) {
            return null;
        }

        return is_numeric( $value ) ? $value + 0 : null;
    }
}
