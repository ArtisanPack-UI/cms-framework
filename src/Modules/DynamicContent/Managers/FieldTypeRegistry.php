<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Contracts\FieldType;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\FieldTypes\AddressFieldType;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\FieldTypes\ImageFieldType;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\FieldTypes\NumberFieldType;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\FieldTypes\RichTextFieldType;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\FieldTypes\ScalarFieldType;

/**
 * Registry of available field-type classes keyed by slug.
 *
 * @since 2.4.0
 */
class FieldTypeRegistry
{
    /**
     * @var array<string, FieldType>
     */
    protected array $types = [];

    public function __construct()
    {
        foreach ( [
            new ScalarFieldType( 'text', 'Text' ),
            new ScalarFieldType( 'url', 'URL' ),
            new ScalarFieldType( 'email', 'Email' ),
            new ScalarFieldType( 'phone', 'Phone' ),
            new ScalarFieldType( 'date', 'Date' ),
            new ScalarFieldType( 'datetime', 'Date & Time' ),
            new ScalarFieldType( 'select', 'Select' ),
            new RichTextFieldType(),
            new ImageFieldType(),
            new NumberFieldType(),
            new AddressFieldType(),
        ] as $type ) {
            $this->register( $type );
        }
    }

    public function register( FieldType $type ): void
    {
        $this->types[ $type->slug() ] = $type;
    }

    public function get( string $slug ): ?FieldType
    {
        return $this->types[ $slug ] ?? null;
    }

    /**
     * @return array<string, FieldType>
     */
    public function all(): array
    {
        return $this->types;
    }
}
